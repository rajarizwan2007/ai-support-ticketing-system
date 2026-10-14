import { Form, Head, router } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { destroy, index, store, update } from '@/routes/kb';
import type { Category, KbArticle, KbArticleStatus } from '@/types';

type Props = {
    article?: KbArticle;
    categories: Category[];
    statuses: KbArticleStatus[];
};

const fieldClassName = 'rounded-md border bg-background p-2 text-sm';

/**
 * Create and edit share one page: with `article` it updates, without it creates.
 */
export default function KbForm({ article, categories, statuses }: Props) {
    const remove = () => {
        if (article && confirm(`Delete "${article.title}"?`)) {
            router.delete(destroy.url(article.id));
        }
    };

    return (
        <>
            <Head title={article ? article.title : 'New article'} />
            <Form
                {...(article ? update.form(article.id) : store.form())}
                options={{ preserveScroll: true }}
                className="flex max-w-3xl flex-col gap-4 p-4"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-2">
                            <Label htmlFor="title">Title</Label>
                            <Input
                                id="title"
                                name="title"
                                defaultValue={article?.title}
                                required
                            />
                            <InputError message={errors.title} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="body">Body</Label>
                            <textarea
                                id="body"
                                name="body"
                                rows={14}
                                defaultValue={article?.body}
                                required
                                className={fieldClassName}
                            />
                            <InputError message={errors.body} />
                        </div>

                        <div className="flex gap-4">
                            <div className="grid gap-2">
                                <Label htmlFor="category_id">Category</Label>
                                <select
                                    id="category_id"
                                    name="category_id"
                                    defaultValue={article?.category_id ?? ''}
                                    className={fieldClassName}
                                >
                                    <option value="">None</option>
                                    {categories.map((category) => (
                                        <option
                                            key={category.id}
                                            value={category.id}
                                        >
                                            {category.name}
                                        </option>
                                    ))}
                                </select>
                                <InputError message={errors.category_id} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="status">Status</Label>
                                <select
                                    id="status"
                                    name="status"
                                    defaultValue={article?.status ?? 'draft'}
                                    className={fieldClassName}
                                >
                                    {statuses.map((status) => (
                                        <option key={status} value={status}>
                                            {status}
                                        </option>
                                    ))}
                                </select>
                                <InputError message={errors.status} />
                            </div>
                        </div>

                        {article?.published_at && (
                            <p className="text-sm text-muted-foreground">
                                Published{' '}
                                {new Date(
                                    article.published_at,
                                ).toLocaleDateString()}
                            </p>
                        )}

                        <div className="flex gap-2">
                            <Button type="submit" disabled={processing}>
                                {processing && <Spinner />}
                                {article ? 'Save' : 'Create article'}
                            </Button>
                            {article && (
                                <Button
                                    type="button"
                                    variant="destructive"
                                    onClick={remove}
                                >
                                    Delete
                                </Button>
                            )}
                        </div>
                    </>
                )}
            </Form>
        </>
    );
}

KbForm.layout = {
    breadcrumbs: [{ title: 'Knowledge base', href: index() }],
};
