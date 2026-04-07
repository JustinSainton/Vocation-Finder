export interface TextBlock {
    type: 'text';
    content: string;
}

export interface ReflectionBlock {
    type: 'reflection';
    prompt: string;
}

export interface CheckpointBlock {
    type: 'checkpoint';
    question: string;
    options: string[];
}

export interface VideoBlock {
    type: 'video';
    url: string;
    title: string;
}

export interface ImageBlock {
    type: 'image';
    media_id: string | null;
    url: string;
    alt: string;
}

export interface PdfBlock {
    type: 'pdf';
    media_id: string | null;
    url: string;
    title: string;
    original_filename: string;
}

export type ContentBlock = TextBlock | ReflectionBlock | CheckpointBlock | VideoBlock | ImageBlock | PdfBlock;

export type BlockType = ContentBlock['type'];

export const BLOCK_TYPE_LABELS: Record<BlockType, string> = {
    text: 'Text',
    reflection: 'Reflection',
    checkpoint: 'Checkpoint',
    video: 'Video',
    image: 'Image',
    pdf: 'PDF',
};

export const BLOCK_TYPE_DESCRIPTIONS: Record<BlockType, string> = {
    text: 'Rich text content',
    reflection: 'A prompt for learners to reflect on',
    checkpoint: 'A question with multiple choice options',
    video: 'Embed a YouTube or Vimeo video',
    image: 'Upload an image or paste a URL',
    pdf: 'Upload a PDF document',
};

export function createEmptyBlock(type: BlockType): ContentBlock {
    switch (type) {
        case 'text':
            return { type: 'text', content: '' };
        case 'reflection':
            return { type: 'reflection', prompt: '' };
        case 'checkpoint':
            return { type: 'checkpoint', question: '', options: ['', ''] };
        case 'video':
            return { type: 'video', url: '', title: '' };
        case 'image':
            return { type: 'image', media_id: null, url: '', alt: '' };
        case 'pdf':
            return { type: 'pdf', media_id: null, url: '', title: '', original_filename: '' };
    }
}
