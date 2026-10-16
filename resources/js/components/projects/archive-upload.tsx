import { Form } from '@/components/ui/form';
import { Link, useForm } from "@inertiajs/react";
import { FileArchive, GitBranch, ShieldCheck, UploadCloud } from "lucide-react";
import { useRef, useState, type DragEvent, type FormEvent } from "react";
import { Alert } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { cn } from "@/lib/cn";
import { notify } from "@/lib/notify";

interface EnvironmentOption {
    uuid: string;
    name: string;
    slug: string;
}

export function ArchiveUpload({ basePath, projectUuid, environments, available, maxBytes, executionMode }: {
    basePath: string;
    projectUuid: string;
    environments: EnvironmentOption[];
    available: boolean;
    maxBytes: number;
    executionMode: string;
}) {
    const [environmentUuid, setEnvironmentUuid] = useState(environments[0]?.uuid ?? "");
    const [reviewing, setReviewing] = useState(false);
    const [dragging, setDragging] = useState(false);
    const inputRef = useRef<HTMLInputElement>(null);
    const form = useForm<{ archive: File | null }>({ archive: null });

    function chooseFile(file: File | undefined) {
        form.clearErrors("archive");

        if (!file) {
            form.setData("archive", null);
            return;
        }

        if (!file.name.toLowerCase().endsWith(".zip")) {
            form.setError("archive", "فقط فایل با پسوند ZIP پذیرفته می‌شود.");
            form.setData("archive", null);
            return;
        }

        if (file.size > maxBytes) {
            form.setError("archive", `حجم فایل نباید بیشتر از ${formatBytes(maxBytes)} باشد.`);
            form.setData("archive", null);
            return;
        }

        form.setData("archive", file);
    }

    function drop(event: DragEvent<HTMLLabelElement>) {
        event.preventDefault();
        setDragging(false);

        if (event.dataTransfer.files.length !== 1) {
            form.setError("archive", "هر بار فقط یک فایل ZIP قابل بارگذاری است.");
            form.setData("archive", null);
            return;
        }

        chooseFile(event.dataTransfer.files.item(0) ?? undefined);
    }

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        if (!form.data.archive || !environmentUuid) {
            form.setError("archive", "فایل ZIP و محیط مقصد را انتخاب کنید.");
            return;
        }

        if (!reviewing) { setReviewing(true); return; }

        form.post(`${basePath}/projects/${projectUuid}/environments/${environmentUuid}/source-archives`, {
            forceFormData: true,
            preserveScroll: true,
            onError: (errors) => { setReviewing(false); notify.formErrors(errors); },
            onSuccess: () => {
                form.reset();
                setReviewing(false);
                if (inputRef.current) inputRef.current.value = "";
            },
        });
    }

    return (
        <Card id="archive-upload" className="overflow-hidden p-0">
            <div className="border-b border-line bg-[linear-gradient(135deg,var(--color-accent-soft),transparent_70%)] p-4 sm:p-5">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p className="text-xs font-semibold text-accent">منبع کد پروژه</p>
                        <h2 className="mt-1 text-xl font-semibold">استقرار از Git یا فایل ZIP</h2>
                        <p className="mt-1 max-w-2xl text-sm leading-7 text-muted">روش دریافت کد را بررسی کنید؛ برای فایل ZIP، مقصد و فایل را انتخاب و سپس درخواست را تأیید کنید.</p>
                    </div>
                    <Badge tone="info">آپلود خصوصی</Badge>
                </div>
            </div>

            <div className="space-y-5 p-4 sm:p-5">
                <div className="grid gap-3 md:grid-cols-2" aria-label="روش‌های دریافت کد پروژه">
                    <div className="rounded-2xl border border-line bg-surface-muted/60 p-4">
                        <div className="flex items-center justify-between gap-3">
                            <span className="grid size-10 place-items-center rounded-xl bg-surface text-muted shadow-sm"><GitBranch className="size-5" aria-hidden="true" /></span>
                            <Badge>در حال ساخت</Badge>
                        </div>
                        <h3 className="mt-3 font-semibold">GitHub، GitLab و GitLab شخصی</h3>
                        <p className="mt-1 text-sm leading-7 text-muted">اتصال حساب و انتخاب مخزن‌ها آماده است؛ اتصال منبع به این پروژه هنوز از این فرم انجام نمی‌شود.</p><Button asChild variant="secondary" className="mt-3"><Link href={`${basePath}/git`}>مدیریت حساب‌های گیت</Link></Button>
                    </div>
                    <div className="rounded-2xl border border-accent/35 bg-accent-soft/55 p-4 shadow-[inset_0_0_0_1px_color-mix(in_srgb,var(--color-accent)_8%,transparent)]">
                        <div className="flex items-center justify-between gap-3">
                            <span className="grid size-10 place-items-center rounded-xl bg-surface text-accent shadow-sm"><FileArchive className="size-5" aria-hidden="true" /></span>
                            <Badge tone="success">آماده آپلود</Badge>
                        </div>
                        <h3 className="mt-3 font-semibold">فایل ZIP پروژه</h3>
                        <p className="mt-1 text-sm leading-7 text-muted">فایل بررسی می‌شود، در فضای غیرعمومی می‌ماند و برای محیط انتخابی یک عملیات استقرار دستی ثبت می‌کند.</p>
                    </div>
                </div>

                {executionMode === "disabled" ? <Alert tone="warning">اجرای میزبان خاموش است. آپلود، بررسی امنیتی و ثبت عملیات انجام می‌شود، اما هیچ build یا فعال‌سازی موفق اعلام نخواهد شد.</Alert> : null}

                {!available ? (
                    <Alert tone="neutral">نقش مشاهده‌گر اجازه آپلود منبع یا شروع استقرار پروژه را ندارد.</Alert>
                ) : environments.length === 0 ? (
                    <Alert tone="neutral">برای آپلود ZIP ابتدا باید حداقل یک environment واقعی برای پروژه ثبت شود.</Alert>
                ) : (
                    <Form className="space-y-4" onSubmit={submit}>
                        <ol className="grid grid-cols-2 gap-2 text-xs" aria-label="مراحل دریافت منبع"><li aria-current={!reviewing ? 'step' : undefined} className={`border-t-2 pt-3 ${!reviewing ? 'border-accent text-accent' : 'border-line text-muted'}`}>۱. انتخاب فایل و محیط</li><li aria-current={reviewing ? 'step' : undefined} className={`border-t-2 pt-3 ${reviewing ? 'border-accent text-accent' : 'border-line text-muted'}`}>۲. بررسی و ثبت درخواست</li></ol>
                        <div hidden={reviewing} className="space-y-4">
                        <label className="block text-sm" htmlFor="archive-environment">
                            <span className="mb-1.5 block font-medium text-ink">محیط مقصد</span>
                            <select id="archive-environment" className="min-h-11 w-full rounded-xl border border-line bg-surface px-3.5 py-2 text-ink shadow-sm outline-none focus:border-accent focus:ring-4 focus:ring-accent/10" value={environmentUuid} onChange={(event) => setEnvironmentUuid(event.target.value)}>
                                {environments.map((environment) => <option key={environment.uuid} value={environment.uuid}>{environment.name} ({environment.slug})</option>)}
                            </select>
                        </label>

                        <label
                            htmlFor="project-archive"
                            className={cn("group block rounded-2xl border-2 border-dashed px-5 py-8 text-center transition-[border-color,background-color,transform]", dragging ? "border-accent bg-accent-soft/70" : "border-line bg-surface-muted/45 hover:border-accent/45 hover:bg-accent-soft/25", form.processing && "pointer-events-none opacity-60")}
                            onDragEnter={(event) => { event.preventDefault(); setDragging(true); }}
                            onDragOver={(event) => event.preventDefault()}
                            onDragLeave={() => setDragging(false)}
                            onDrop={drop}
                        >
                            <input ref={inputRef} id="project-archive" name="archive" type="file" className="sr-only" accept=".zip,application/zip" disabled={form.processing} onChange={(event) => chooseFile(event.target.files?.item(0) ?? undefined)} />
                            <span className="mx-auto grid size-14 place-items-center rounded-2xl border border-line bg-surface text-accent shadow-sm transition-transform group-hover:-translate-y-0.5">
                                {form.data.archive ? <FileArchive className="size-6" aria-hidden="true" /> : <UploadCloud className="size-6" aria-hidden="true" />}
                            </span>
                            <span className="mt-4 block font-semibold">{form.data.archive ? form.data.archive.name : "فایل ZIP را اینجا رها کنید یا برای انتخاب کلیک کنید"}</span>
                            <span className="mt-1 block text-sm text-muted">{form.data.archive ? formatBytes(form.data.archive.size) : `حداکثر ${formatBytes(maxBytes)}؛ فقط یک فایل ZIP`}</span>
                        </label>

                        </div>
                        {reviewing && form.data.archive ? <div className="rounded-xl border border-line bg-surface-muted p-4"><h3 className="font-semibold">بررسی درخواست</h3><dl className="mt-3 grid gap-3 text-sm sm:grid-cols-2"><div><dt className="text-muted">فایل</dt><dd className="break-all">{form.data.archive.name}</dd></div><div><dt className="text-muted">اندازه</dt><dd>{formatBytes(form.data.archive.size)}</dd></div><div><dt className="text-muted">محیط مقصد</dt><dd>{environments.find(environment => environment.uuid === environmentUuid)?.name}</dd></div></dl><p className="mt-4 text-xs leading-7 text-muted">فایل پس از ارسال بررسی می‌شود. ثبت درخواست به معنی اجرای موفق یا فعال‌شدن نسخه نیست.</p><Button className="mt-3" variant="secondary" disabled={form.processing} onClick={() => setReviewing(false)}>ویرایش انتخاب‌ها</Button></div> : null}
                        {form.errors.archive ? <p className="text-sm text-danger" role="alert">{form.errors.archive}</p> : null}
                        {form.progress ? (
                            <div className="space-y-1.5" role="status" aria-live="polite">
                                <div className="flex items-center justify-between gap-3 text-xs text-muted"><span>در حال ارسال امن فایل</span><span className="font-mono" dir="ltr">{form.progress.percentage ?? 0}%</span></div>
                                <div className="h-2 overflow-hidden rounded-full bg-surface-muted"><div className="h-full rounded-full bg-accent transition-[width]" style={{ width: `${form.progress.percentage ?? 0}%` }} /></div>
                            </div>
                        ) : null}

                        <div className="flex flex-col gap-3 rounded-2xl border border-line bg-surface-muted/55 p-3 text-xs leading-6 text-muted sm:flex-row sm:items-center sm:justify-between">
                            <span className="flex items-start gap-2"><ShieldCheck className="mt-0.5 size-4 shrink-0 text-success" aria-hidden="true" />مسیرهای خروجی، symlink، فایل رمز‌شده، تعداد ورودی و بمب فشرده‌سازی پیش از ذخیره رد می‌شوند.</span>
                            <Button type="submit" loading={form.processing} loadingLabel="در حال آپلود" disabled={!form.data.archive}>{reviewing ? "تأیید، آپلود و ثبت درخواست" : "بررسی و ادامه"}</Button>
                        </div>
                    </Form>
                )}
            </div>
        </Card>
    );
}

function formatBytes(bytes: number): string {
    if (bytes < 1024) return `${bytes.toLocaleString("fa-IR")} بایت`;
    const units = ["KiB", "MiB", "GiB"];
    let value = bytes / 1024;
    let unit = units[0];
    for (let index = 1; index < units.length && value >= 1024; index += 1) {
        value /= 1024;
        unit = units[index];
    }
    return `${new Intl.NumberFormat("fa-IR", { maximumFractionDigits: 1 }).format(value)} ${unit}`;
}
