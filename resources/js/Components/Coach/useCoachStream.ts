import { useCallback, useRef, useState } from 'react';
import type { Settled, StreamEvent, Support } from './types';

type Phase = 'idle' | 'thinking' | 'speaking';

interface Handlers {
    onSettled: (settled: Settled) => void;
    onSupport: (support: Support) => void;
    onError: (message: string) => void;
}

function xsrfToken(): string {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

/**
 * One coach turn over server-sent events.
 *
 * The coach is honest about what it is doing while it is not yet talking —
 * "Rereading your answers", not a spinner — and then its words arrive as they
 * are written. The server's `done` event carries the thread as stored, which
 * replaces the optimistic copy, so what is on screen is always what was kept.
 *
 * JSON responses are the non-streamed cases: a crisis message, an opener that
 * was already given, or a refusal.
 */
export function useCoachStream({ onSettled, onSupport, onError }: Handlers) {
    const [phase, setPhase] = useState<Phase>('idle');
    const [status, setStatus] = useState<string>('');
    const [draft, setDraft] = useState<string>('');
    const abortRef = useRef<AbortController | null>(null);

    const run = useCallback(
        async (url: string, body: Record<string, string> = {}): Promise<boolean> => {
            abortRef.current?.abort();
            const controller = new AbortController();
            abortRef.current = controller;

            setPhase('thinking');
            setStatus('');
            setDraft('');

            const finish = () => {
                setPhase('idle');
                setStatus('');
                setDraft('');
            };

            try {
                const response = await fetch(url, {
                    method: 'POST',
                    credentials: 'same-origin',
                    signal: controller.signal,
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'text/event-stream, application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-XSRF-TOKEN': xsrfToken(),
                    },
                    body: JSON.stringify(body),
                });

                const type = response.headers.get('Content-Type') ?? '';

                if (!type.includes('text/event-stream')) {
                    const json = (await response.json().catch(() => ({}))) as Partial<Settled> & {
                        support?: Support;
                        message?: string;
                        errors?: Record<string, string[]>;
                    };

                    finish();

                    if (json.support) {
                        onSupport(json.support);
                        return true;
                    }
                    if (response.ok && json.items) {
                        onSettled(json as Settled);
                        return true;
                    }
                    onError(json.message ?? 'Something went wrong. Try again.');
                    return false;
                }

                const reader = response.body?.getReader();
                if (!reader) {
                    finish();
                    onError('Something went wrong. Try again.');
                    return false;
                }

                const decoder = new TextDecoder();
                let buffer = '';
                let settled = false;
                let failed = false;

                const handle = (event: StreamEvent) => {
                    switch (event.type) {
                        case 'status':
                            setStatus(event.label);
                            break;
                        case 'delta':
                            setPhase('speaking');
                            setDraft((current) => current + event.text);
                            break;
                        case 'done':
                            settled = true;
                            onSettled(event);
                            break;
                        case 'error':
                            failed = true;
                            onError(event.message);
                            break;
                    }
                };

                for (;;) {
                    const { value, done } = await reader.read();
                    if (done) break;
                    buffer += decoder.decode(value, { stream: true });

                    let boundary = buffer.indexOf('\n\n');
                    while (boundary !== -1) {
                        const chunk = buffer.slice(0, boundary);
                        buffer = buffer.slice(boundary + 2);
                        if (chunk.startsWith('data: ')) {
                            try {
                                handle(JSON.parse(chunk.slice(6)) as StreamEvent);
                            } catch {
                                // A malformed frame is skipped; `done` is authoritative.
                            }
                        }
                        boundary = buffer.indexOf('\n\n');
                    }
                }

                finish();

                if (!settled && !failed) {
                    onError('The connection dropped before the coach finished. Try again.');
                }

                return settled;
            } catch (error) {
                finish();
                if ((error as Error).name !== 'AbortError') {
                    onError('Could not reach your coach. Check your connection and try again.');
                }
                return false;
            }
        },
        [onSettled, onSupport, onError],
    );

    return { run, phase, status, draft, busy: phase !== 'idle' };
}
