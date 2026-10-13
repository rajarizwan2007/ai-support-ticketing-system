import { Form, Head } from '@inertiajs/react';
import AttachmentInput from '@/components/attachment-input';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { create, index, store } from '@/routes/tickets';
import type { Category, Priority } from '@/types';

type Props = {
    categories: Category[];
    priorities: Priority[];
};

const fieldClassName = 'rounded-md border bg-background p-2 text-sm';

export default function TicketsCreate({ categories, priorities }: Props) {
    return (
        <>
            <Head title="New ticket" />
            <Form
                {...store.form()}
                className="flex max-w-3xl flex-col gap-4 p-4"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-2">
                            <Label htmlFor="subject">Subject</Label>
                            <Input id="subject" name="subject" required />
                            <InputError message={errors.subject} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="description">Description</Label>
                            <textarea
                                id="description"
                                name="description"
                                rows={6}
                                required
                                className={fieldClassName}
                            />
                            <InputError message={errors.description} />
                        </div>

                        <div className="flex gap-4">
                            <div className="grid gap-2">
                                <Label htmlFor="category_id">Category</Label>
                                <select
                                    id="category_id"
                                    name="category_id"
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
                                <Label htmlFor="priority">Priority</Label>
                                <select
                                    id="priority"
                                    name="priority"
                                    defaultValue="medium"
                                    className={fieldClassName}
                                >
                                    {priorities.map((priority) => (
                                        <option key={priority} value={priority}>
                                            {priority}
                                        </option>
                                    ))}
                                </select>
                                <InputError message={errors.priority} />
                            </div>
                        </div>

                        <div className="grid gap-2">
                            <Label>Attachments</Label>
                            <AttachmentInput errors={errors} />
                        </div>

                        <Button
                            type="submit"
                            disabled={processing}
                            className="self-start"
                        >
                            Create ticket
                        </Button>
                    </>
                )}
            </Form>
        </>
    );
}

TicketsCreate.layout = {
    breadcrumbs: [
        { title: 'Tickets', href: index() },
        { title: 'New ticket', href: create() },
    ],
};
