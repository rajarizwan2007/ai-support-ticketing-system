import type { Category, UserSummary } from './tickets';

export type KbArticleStatus = 'draft' | 'published' | 'archived';

export type KbArticle = {
    id: number;
    title: string;
    slug: string;
    body: string;
    status: KbArticleStatus;
    category_id: number | null;
    category?: Category | null;
    author?: UserSummary;
    published_at: string | null;
    updated_at: string;
};
