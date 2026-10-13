import InputError from '@/components/input-error';

/**
 * File picker for up to 3 attachments (5 MB each), with the first
 * error the server returned for the list or for any single file.
 */
export default function AttachmentInput({
    errors,
}: {
    errors: Record<string, string>;
}) {
    const message =
        errors.attachments ??
        Object.entries(errors).find(([key]) =>
            key.startsWith('attachments.'),
        )?.[1];

    return (
        <div className="grid gap-2">
            <input
                type="file"
                name="attachments[]"
                multiple
                aria-label="Attachments"
                className="text-sm"
            />
            <InputError message={message} />
        </div>
    );
}
