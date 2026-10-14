import { Fragment } from 'react';

// Repository Markdown subset; text is rendered by React, never as raw HTML.
export function TutorialContent({ content }: { content: string }) {
    const blocks = content.split(/(```[\s\S]*?```)/g).filter(Boolean);
    return <div className="space-y-5 leading-8 text-ink">{blocks.map((block, index) => {
        if (block.startsWith('```')) {
            const code = block.replace(/^```[^\n]*\n/, '').replace(/```$/, '').trimEnd();
            return <pre key={index} dir="ltr" className="max-w-full overflow-x-auto rounded-xl border border-line bg-surface-muted p-4 text-left font-mono text-sm"><code>{code}</code></pre>;
        }
        return <Fragment key={index}>{block.trim().split(/\n\s*\n/).filter(Boolean).map((paragraph, n) => {
            if (paragraph.startsWith('## ')) return <h2 key={n} className="mt-6 text-lg font-semibold">{paragraph.slice(3)}</h2>;
            return <p key={n} className="break-words">{paragraph}</p>;
        })}</Fragment>;
    })}</div>;
}
