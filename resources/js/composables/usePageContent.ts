import { Readability } from '@mozilla/readability';
import TurndownService from 'turndown';

export function usePageContent() {
    const extractPageContent = (): string | null => {
        try {
            // Clone document for Readability (it modifies the DOM)
            const documentClone = document.cloneNode(true) as Document;

            // Use Readability to extract main content
            const reader = new Readability(documentClone);
            const article = reader.parse();

            if (!article || !article.content) {
                return null;
            }

            // Convert HTML to Markdown
            const turndown = new TurndownService({
                headingStyle: 'atx',
                codeBlockStyle: 'fenced',
            });

            const markdown = turndown.turndown(article.content);

            // Limit to ~50KB to prevent oversized payloads
            return markdown.slice(0, 50000);
        } catch (error) {
            console.error('Failed to extract page content:', error);
            return null;
        }
    };

    return { extractPageContent };
}
