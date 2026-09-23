/**
 * The vocational engine returns prose with light Markdown emphasis — `*scale*`,
 * `*generation*`, `*normalize*` — because that is the natural way for a model to
 * mark a stressed word. Nothing here parsed it, so the asterisks rendered
 * literally on screen.
 *
 * This is deliberately not a Markdown parser. It understands the two spans the
 * engine actually emits, and leaves everything else — including a lone `*` in a
 * student's own writing — untouched.
 */
export interface EmphasisSpan {
  text: string;
  bold: boolean;
  italic: boolean;
}

const TOKEN = /(\*\*[^*]+\*\*|\*[^*]+\*)/g;

export function splitEmphasis(input: string): EmphasisSpan[] {
  return input
    .split(TOKEN)
    .filter((part) => part !== '')
    .map((part) => {
      if (part.length > 4 && part.startsWith('**') && part.endsWith('**')) {
        return { text: part.slice(2, -2), bold: true, italic: false };
      }

      if (part.length > 2 && part.startsWith('*') && part.endsWith('*')) {
        return { text: part.slice(1, -1), bold: false, italic: true };
      }

      return { text: part, bold: false, italic: false };
    });
}

export function hasEmphasis(input: string): boolean {
  return input.includes('*');
}
