import AppLayout from '../../Layouts/AppLayout';
import { Link } from '@inertiajs/react';
import { FormEvent, useEffect, useRef, useState } from 'react';

interface Message {
    role: 'user' | 'assistant';
    content: string;
}

export default function CareerCoachIndex() {
    const [messages, setMessages] = useState<Message[]>([]);
    const [input, setInput] = useState('');
    const [loading, setLoading] = useState(false);
    const [conversationId, setConversationId] = useState<string | null>(null);
    const [started, setStarted] = useState(false);
    const scrollRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        if (scrollRef.current) {
            scrollRef.current.scrollTop = scrollRef.current.scrollHeight;
        }
    }, [messages]);

    const startConversation = async () => {
        setLoading(true);
        try {
            const res = await fetch('/api/v1/career-coach/start', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
            });
            const data = await res.json();
            setConversationId(data.conversation_id);
            setMessages([{ role: 'assistant', content: data.message }]);
            setStarted(true);
        } catch {
            setMessages([{ role: 'assistant', content: 'Sorry, I had trouble starting. Please try again.' }]);
        } finally {
            setLoading(false);
        }
    };

    const sendMessage = async (e: FormEvent) => {
        e.preventDefault();
        if (!input.trim() || !conversationId || loading) return;

        const text = input.trim();
        setInput('');
        setMessages((prev) => [...prev, { role: 'user', content: text }]);
        setLoading(true);

        try {
            const res = await fetch('/api/v1/career-coach/message', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({ conversation_id: conversationId, message: text }),
            });
            const data = await res.json();
            setMessages((prev) => [...prev, { role: 'assistant', content: data.message }]);
        } catch {
            setMessages((prev) => [...prev, { role: 'assistant', content: 'Something went wrong. Could you try again?' }]);
        } finally {
            setLoading(false);
        }
    };

    return (
        <AppLayout title="Career Coach">
            <div className="flex items-center justify-between">
                <div>
                    <h1 className="font-serif text-2xl tracking-tight text-[var(--color-text)]">
                        Career Coach
                    </h1>
                    <p className="mt-1 text-sm text-[var(--color-text-secondary)]">
                        Explore career paths aligned with your vocational calling.
                    </p>
                </div>
                <Link
                    href="/jobs"
                    className="text-xs text-[var(--color-text-secondary)] underline hover:text-[var(--color-text)]"
                >
                    Browse Jobs
                </Link>
            </div>

            <div className="my-6 h-px bg-[var(--color-divider)]" />

            {!started ? (
                <div className="py-16 text-center">
                    <p className="font-serif text-lg text-[var(--color-text)]">
                        Ready to explore your career path?
                    </p>
                    <p className="mx-auto mt-3 max-w-sm text-sm leading-relaxed text-[var(--color-text-secondary)]">
                        Your AI career coach knows your vocational profile and can search real
                        job listings. Together, you'll explore roles that align with your calling.
                    </p>
                    <button
                        onClick={startConversation}
                        disabled={loading}
                        className="mt-8 bg-[var(--color-text)] px-8 py-3 text-xs tracking-wide text-[var(--color-background)] disabled:opacity-50"
                    >
                        {loading ? 'Starting...' : 'Start Coaching Session'}
                    </button>
                </div>
            ) : (
                <div className="flex flex-col" style={{ minHeight: '60vh' }}>
                    <div
                        ref={scrollRef}
                        className="flex-1 space-y-4 overflow-y-auto pb-4"
                        style={{ maxHeight: '55vh' }}
                    >
                        {messages.map((msg, i) => (
                            <div key={i} className={`flex ${msg.role === 'user' ? 'justify-end' : 'justify-start'}`}>
                                <div className={`max-w-[85%] px-4 py-3 text-sm leading-relaxed ${
                                    msg.role === 'user'
                                        ? 'bg-[var(--color-text)] text-[var(--color-background)]'
                                        : 'border border-[var(--color-border)] text-[var(--color-text)]'
                                }`}>
                                    {msg.content.split('\n').map((line, j) => (
                                        <p key={j} className={j > 0 ? 'mt-2' : ''}>{line}</p>
                                    ))}
                                </div>
                            </div>
                        ))}
                        {loading && (
                            <div className="flex justify-start">
                                <div className="border border-[var(--color-border)] px-4 py-3 text-sm text-[var(--color-accent)]">
                                    Thinking...
                                </div>
                            </div>
                        )}
                    </div>

                    <form onSubmit={sendMessage} className="mt-4 flex gap-2">
                        <input
                            type="text"
                            value={input}
                            onChange={(e) => setInput(e.target.value)}
                            placeholder="Ask about careers, job types, or specific opportunities..."
                            disabled={loading}
                            className="flex-1 border border-[var(--color-border)] bg-transparent px-3 py-3 text-sm text-[var(--color-text)] placeholder:text-[var(--color-accent)] disabled:opacity-50"
                            autoFocus
                        />
                        <button
                            type="submit"
                            disabled={loading || !input.trim()}
                            className="bg-[var(--color-text)] px-6 py-3 text-xs tracking-wide text-[var(--color-background)] disabled:opacity-50"
                        >
                            Send
                        </button>
                    </form>
                </div>
            )}
        </AppLayout>
    );
}
