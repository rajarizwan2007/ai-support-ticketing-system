import { Form, Head, Link } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { create, edit, index } from '@/routes/kb';
import type { KbArticle } from '@/types';

type Props = {
    articles: {
        data: KbArticle[];
        current_page: number;
        last_page: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    filters: { search?: string };
};

export default function KbIndex({ articles, filters }: Props) {
    return (
        <>
            <Head title="Knowledge base" />
            <div className="p-4">
                <Button asChild className="mb-4">
                    <Link href={create()}>New article</Link>
                </Button>

                <Form
                    {...index.form()}
                    options={{ preserveState: true }}
                    className="mb-4 flex gap-2"
                >
                    <Input
                        name="search"
                        defaultValue={filters.search}
                        placeholder="Search titles"
                        aria-label="Search"
                        className="w-64"
                    />
                    <Button type="submit" variant="secondary">
                        Search
                    </Button>
                    <Button asChild variant="ghost">
                        <Link href={index()}>Clear</Link>
                    </Button>
                </Form>

                <table className="w-full text-left text-sm">
                    <thead className="border-b text-muted-foreground">
                        <tr>
                            <th className="p-2">Title</th>
                            <th className="p-2">Status</th>
                            <th className="p-2">Category</th>
                            <th className="p-2">Author</th>
                            <th className="p-2">Updated</th>
                        </tr>
                    </thead>
                    <tbody>
                        {articles.data.map((article) => (
                            <tr
                                key={article.id}
                                className="border-b hover:bg-muted/50"
                            >
                                <td className="p-2 font-medium">
                                    <Link href={edit(article.id)}>
                                        {article.title}
                                    </Link>
                                </td>
                                <td className="p-2">
                                    <Badge variant="secondary">
                                        {article.status}
                                    </Badge>
                                </td>
                                <td className="p-2">
                                    {article.category?.name ?? '—'}
                                </td>
                                <td className="p-2">{article.author?.name}</td>
                                <td className="p-2">
                                    {new Date(
                                        article.updated_at,
                                    ).toLocaleDateString()}
                                </td>
                            </tr>
                        ))}
                        {articles.data.length === 0 && (
                            <tr>
                                <td
                                    colSpan={5}
                                    className="p-8 text-center text-muted-foreground"
                                >
                                    {filters.search
                                        ? 'No articles match this search.'
                                        : 'No articles yet.'}
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>

                <div className="mt-4 flex items-center gap-4 text-sm">
                    {articles.prev_page_url && (
                        <Link href={articles.prev_page_url}>Previous</Link>
                    )}
                    <span className="text-muted-foreground">
                        Page {articles.current_page} of {articles.last_page}
                    </span>
                    {articles.next_page_url && (
                        <Link href={articles.next_page_url}>Next</Link>
                    )}
                </div>
            </div>
        </>
    );
}

KbIndex.layout = {
    breadcrumbs: [{ title: 'Knowledge base', href: index() }],
};
