import { Checkbox } from '@/components/ui/checkbox';
import { useForm } from '@inertiajs/react';
import { Card } from '@/components/ui/card';
import { Form } from '@/components/ui/form';
import { Button } from '@/components/ui/button';

export interface Repository {
    uuid: string;
    full_name: string;
    default_branch: string;
    visibility: string;
    archived: boolean;
    disabled: boolean;
    is_selected: boolean;
}
export function RepositorySelection({
    repositories,
    canManage,
    url,
}: {
    repositories: Repository[];
    canManage: boolean;
    url: string;
}) {
    const form = useForm({
        visible_uuids: repositories.map((r) => r.uuid),
        selected_uuids: repositories
            .filter((r) => r.is_selected)
            .map((r) => r.uuid),
    });
    return (
        <Card>
            <h2 className="font-semibold">انتخاب مخزن‌های مجاز</h2>
            <p className="mt-2 text-sm leading-7 text-muted">
                مخزن‌های تازه به‌صورت پیش‌فرض مجاز نیستند. تغییرات هر صفحه را
                پیش از رفتن به صفحهٔ بعد ذخیره کنید. پس از تکمیل دریافت، فهرست
                به‌صورت خودکار تازه می‌شود.
            </p>
            {repositories.length ? (
                <Form
                    className="mt-4"
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.put(`${url}/repositories`, {
                            preserveScroll: true,
                        });
                    }}
                >
                    {canManage ? <div className="mb-4 flex flex-wrap items-center gap-3"><Button variant="secondary" disabled={form.processing} onClick={() => form.setData('selected_uuids', repositories.filter(r => !r.disabled && !r.archived).map(r => r.uuid))}>انتخاب همهٔ مخزن‌های این صفحه</Button><Button variant="secondary" disabled={form.processing} onClick={() => form.setData('selected_uuids', [])}>پاک‌کردن انتخاب‌های این صفحه</Button><span className="text-xs text-muted">{form.data.selected_uuids.length.toLocaleString('fa-IR')} انتخاب</span></div> : null}
                    <div className="space-y-2">
                        {repositories.map((repository) => (
                            <Checkbox
                                key={repository.uuid}
                                className="flex min-h-16 min-w-0 cursor-pointer items-center gap-3 rounded-xl border border-line p-3 hover:bg-surface-muted"
                                    name={`repository-${repository.uuid}`}
                                    disabled={
                                        !canManage ||
                                        repository.archived ||
                                        repository.disabled ||
                                        form.processing
                                    }
                                    checked={form.data.selected_uuids.includes(
                                        repository.uuid,
                                    )}
                                    onChange={(e) =>
                                        form.setData(
                                            "selected_uuids",
                                            e.target.checked
                                                ? [
                                                      ...form.data
                                                          .selected_uuids,
                                                      repository.uuid,
                                                  ]
                                                : form.data.selected_uuids.filter(
                                                      (uuid) =>
                                                          uuid !==
                                                          repository.uuid,
                                                  ),
                                        )
                                    }
                            >
                                <span className="min-w-0 flex-1">
                                    <span
                                        dir="ltr"
                                        className="block break-all font-mono text-sm"
                                    >
                                        {repository.full_name}
                                    </span>
                                    <span className="mt-1 block text-xs text-muted">
                                        {repository.visibility === "private"
                                            ? "خصوصی"
                                            : repository.visibility ===
                                                "internal"
                                              ? "داخلی"
                                              : "عمومی"}{" "}
                                        · شاخهٔ{" "}
                                        <bdi className="font-mono" dir="ltr">
                                            {repository.default_branch}
                                        </bdi>
                                        {repository.archived
                                            ? " · بایگانی‌شده"
                                            : ""}
                                        {repository.disabled
                                            ? " · خارج از دسترسی فعلی سرویس"
                                            : ""}
                                    </span>
                                </span>
                            </Checkbox>
                        ))}
                    </div>
                    {Object.values(form.errors).map((error, i) => (
                        <p
                            key={i}
                            role="alert"
                            className="mt-3 text-sm text-danger"
                        >
                            {error}
                        </p>
                    ))}
                    {canManage ? (
                        <div className="sticky bottom-3 mt-4 flex flex-wrap items-center gap-3 rounded-xl border border-line bg-surface-raised p-3"><Button type="submit" loading={form.processing}>ذخیرهٔ دسترسی‌های این صفحه</Button><span role="status" className="text-xs text-muted">{form.isDirty ? 'تغییرات هنوز ذخیره نشده‌اند' : 'انتخاب‌های ذخیره‌شده'}</span></div>
                    ) : null}
                </Form>
            ) : (
                <p className="py-8 text-center text-sm text-muted">
                    هنوز مخزنی دریافت نشده است. وضعیت دریافت را در تاریخچه
                    ببینید؛ در صورت شکست دوباره تلاش کنید.
                </p>
            )}
        </Card>
    );
}
