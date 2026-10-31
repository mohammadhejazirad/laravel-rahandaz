import { Head, router, useForm, usePage } from '@inertiajs/react';
import { PersianDate } from '@/components/dates/persian-date';
import { Alert } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Form } from '@/components/ui/form';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { StatusBadge } from '@/components/ui/status-badge';
import { AppLayout } from '@/layouts/app-layout';
import type { SharedProps } from '@/types';

interface WorkspaceRow { uuid: string; name: string; slug: string; current: boolean; role: string }
interface MemberRow {
    uuid: string;
    username: string;
    display_name: string;
    role: string;
    role_label: string;
    sections: string[] | null;
    status: string;
    status_label: string;
    last_login_at: string | null;
    owner: boolean;
    manageable: boolean;
}
const roleLabels: Record<string, string> = { admin: 'مدیر', operator: 'اپراتور', viewer: 'مشاهده‌گر' };

function SectionFields({ names, selected, onChange }: { names: Record<string, string>; selected: string[]; onChange: (next: string[]) => void }) {
    return (
        <div className="grid gap-2 sm:grid-cols-2">
            {Object.entries(names).map(([key, label]) => (
                <Checkbox key={key} name={`section-${key}`} checked={selected.includes(key)} onChange={event => onChange(event.target.checked ? [...selected, key] : selected.filter(item => item !== key))}>{label}</Checkbox>
            ))}
        </div>
    );
}

function MemberCard({ member, names, roles }: { member: MemberRow; names: Record<string, string>; roles: string[] }) {
    const base = usePage<SharedProps>().props.app.base_path;
    const sections = useForm({ sections: member.sections ?? Object.keys(names) });
    const profile = useForm({ role: roles.includes(member.role) ? member.role : (roles[0] ?? 'operator'), display_name: member.display_name, confirm_username: '' });
    const password = useForm({ password: '', password_confirmation: '', confirm_username: '' });
    const status = useForm({ status: member.status === 'suspended' ? 'active' : 'suspended', confirm_username: '' });
    return (
        <Card>
            <div className="flex flex-wrap items-center justify-between gap-2">
                <h3 className="font-semibold" dir="ltr">{member.username}</h3>
                <StatusBadge value={member.status} />
            </div>
            <p className="mt-1 text-sm text-muted">{member.display_name} · {member.role_label} · {member.status_label}</p>
            <p className="mt-1 text-sm text-muted">آخرین ورود: <PersianDate iso={member.last_login_at} /></p>
            {member.owner ? <p className="mt-3 text-sm text-muted">مالک به همهٔ بخش‌ها دسترسی دارد و از اینجا تعلیق یا تنزل داده نمی‌شود.</p> : null}
            {!member.manageable && !member.owner ? <p className="mt-3 text-sm text-muted">این حساب از اینجا تغییر نمی‌کند.</p> : null}
            {member.manageable ? (
                <div className="mt-4 space-y-4">
                    <Form className="space-y-3" onSubmit={event => { event.preventDefault(); profile.put(`${base}/access/memberships/${member.uuid}/role`, { preserveScroll: true, onSuccess: () => profile.setData('confirm_username', '') }); }}>
                        <Input name={`display-${member.uuid}`} label="نام نمایشی" isRequired value={profile.data.display_name} onChange={event => profile.setData('display_name', event.target.value)} error={profile.errors.display_name} />
                        <Select name={`role-${member.uuid}`} label="نقش" isRequired value={profile.data.role} onChange={event => profile.setData('role', event.target.value)} error={profile.errors.role}>
                            {roles.map(role => <option key={role} value={role}>{roleLabels[role] ?? role}</option>)}
                        </Select>
                        <Input name={`confirm-role-${member.uuid}`} label="تأیید با نام کاربری" dir="ltr" className="font-mono" isRequired value={profile.data.confirm_username} onChange={event => profile.setData('confirm_username', event.target.value)} error={profile.errors.confirm_username} />
                        <Button type="submit" variant="secondary" loading={profile.processing}>ذخیرهٔ نقش</Button>
                    </Form>
                    <Form className="space-y-3" onSubmit={event => { event.preventDefault(); sections.put(`${base}/access/memberships/${member.uuid}`, { preserveScroll: true }); }}>
                        <SectionFields names={names} selected={sections.data.sections} onChange={next => sections.setData('sections', next)} />
                        {sections.errors.sections ? <p role="alert" className="text-sm text-danger">{sections.errors.sections}</p> : null}
                        <Button type="submit" variant="secondary" loading={sections.processing}>ذخیرهٔ بخش‌ها</Button>
                    </Form>
                    <Form className="space-y-3" onSubmit={event => { event.preventDefault(); password.put(`${base}/access/memberships/${member.uuid}/password`, { preserveScroll: true, onSuccess: () => password.reset() }); }}>
                        <Input name={`password-${member.uuid}`} label="گذرواژهٔ تازه" type="password" autoComplete="new-password" isRequired value={password.data.password} onChange={event => password.setData('password', event.target.value)} error={password.errors.password} />
                        <Input name={`password-confirm-${member.uuid}`} label="تکرار گذرواژه" type="password" autoComplete="new-password" isRequired value={password.data.password_confirmation} onChange={event => password.setData('password_confirmation', event.target.value)} />
                        <Input name={`confirm-password-${member.uuid}`} label="تأیید با نام کاربری" dir="ltr" className="font-mono" isRequired value={password.data.confirm_username} onChange={event => password.setData('confirm_username', event.target.value)} error={password.errors.confirm_username} />
                        <Button type="submit" variant="secondary" loading={password.processing}>تعویض گذرواژه</Button>
                    </Form>
                    <Form className="space-y-3" onSubmit={event => { event.preventDefault(); status.put(`${base}/access/memberships/${member.uuid}/status`, { preserveScroll: true, onSuccess: () => status.setData('confirm_username', '') }); }}>
                        <Input name={`confirm-status-${member.uuid}`} label="تأیید با نام کاربری" dir="ltr" className="font-mono" isRequired value={status.data.confirm_username} onChange={event => status.setData('confirm_username', event.target.value)} error={status.errors.confirm_username} />
                        <Button type="submit" variant="secondary" loading={status.processing}>{member.status === 'suspended' ? 'فعال‌سازی دوباره' : 'تعلیق حساب'}</Button>
                    </Form>
                </div>
            ) : null}
        </Card>
    );
}

export default function AccessIndex({ owner, assignable_roles, sections, workspaces, members }: { owner: boolean; assignable_roles: string[]; sections: Record<string, string>; workspaces: WorkspaceRow[]; members: MemberRow[] }) {
    const base = usePage<SharedProps>().props.app.base_path;
    const workspace = useForm({ name: '', slug: '' });
    const user = useForm({ username: '', display_name: '', password: '', password_confirmation: '', role: assignable_roles.includes('operator') ? 'operator' : (assignable_roles[0] ?? 'operator'), sections: ['dashboard', 'projects'] });

    return (
        <AppLayout title="اعضا و فضای کاری" description="فضای کاری تازه، کاربر، و دسترسی در حد بخش‌های پنل." eyebrow="فضای کاری">
            <Head title="اعضا و فضای کاری" />
            {assignable_roles.length === 0 ? <Alert tone="info">مشاهده برای شما باز است. ساخت کاربر و تغییر دسترسی برای مالک و مدیر است. فضای کاری تازه فقط برای مالک ساخته می‌شود.</Alert> : null}
            <div className="grid gap-4 xl:grid-cols-2">
                <Card>
                    <h2 className="font-semibold">فضاهای کاری</h2>
                    <ul className="mt-3 space-y-2">
                        {workspaces.map(item => (
                            <li key={item.uuid} className="flex flex-wrap items-center justify-between gap-2 border-t border-line py-2">
                                <span>{item.name} <bdi dir="ltr" className="font-mono text-xs text-muted">{item.slug}</bdi></span>
                                {item.current ? <span className="text-sm text-accent">فضای جاری</span> : <Button type="button" variant="secondary" onClick={() => router.post(`${base}/access/workspaces/${item.uuid}/current`)}>ورود</Button>}
                            </li>
                        ))}
                    </ul>
                    <Form className="mt-4 grid gap-3" onSubmit={event => { event.preventDefault(); workspace.post(`${base}/access/workspaces`); }}>
                        <Input name="workspace-name" label="نام فضای کاری" isRequired value={workspace.data.name} onChange={event => workspace.setData('name', event.target.value)} error={workspace.errors.name} />
                        <Input name="workspace-slug" label="شناسه" dir="ltr" className="font-mono" isRequired value={workspace.data.slug} onChange={event => workspace.setData('slug', event.target.value)} error={workspace.errors.slug} />
                        <Button type="submit" loading={workspace.processing} disabled={!owner}>ساخت فضای کاری</Button>
                    </Form>
                </Card>
                <Card>
                    <h2 className="font-semibold">کاربر تازه</h2>
                    <p className="mt-2 text-sm leading-7 text-muted">دسترسی‌ها به اندازهٔ بخش‌های سایدبار هستند، نه مجوز تک‌تک دکمه‌ها. کاربر در ورود بعدی گذرواژه را عوض می‌کند.</p>
                    <Form className="mt-4 grid gap-3" onSubmit={event => { event.preventDefault(); user.post(`${base}/access/users`, { preserveScroll: true, onSuccess: () => user.reset('password', 'password_confirmation') }); }}>
                        <Input name="username" label="نام کاربری" dir="ltr" className="font-mono" isRequired value={user.data.username} onChange={event => user.setData('username', event.target.value)} error={user.errors.username} />
                        <Input name="display_name" label="نام نمایشی" value={user.data.display_name} onChange={event => user.setData('display_name', event.target.value)} error={user.errors.display_name} />
                        <Select name="role" label="نقش" isRequired value={user.data.role} onChange={event => user.setData('role', event.target.value)} error={user.errors.role} disabled={assignable_roles.length === 0}>
                            {assignable_roles.map(role => <option key={role} value={role}>{roleLabels[role] ?? role}</option>)}
                        </Select>
                        <Input name="password" label="گذرواژهٔ اولیه" type="password" autoComplete="new-password" isRequired value={user.data.password} onChange={event => user.setData('password', event.target.value)} error={user.errors.password} />
                        <Input name="password_confirmation" label="تکرار گذرواژه" type="password" autoComplete="new-password" isRequired value={user.data.password_confirmation} onChange={event => user.setData('password_confirmation', event.target.value)} />
                        <SectionFields names={sections} selected={user.data.sections} onChange={next => user.setData('sections', next)} />
                        {user.errors.sections ? <p role="alert" className="text-sm text-danger">{user.errors.sections}</p> : null}
                        <Button type="submit" loading={user.processing} disabled={assignable_roles.length === 0}>ساخت کاربر</Button>
                    </Form>
                </Card>
            </div>
            <div className="grid gap-4 lg:grid-cols-2">
                {members.map(member => <MemberCard key={member.uuid} member={member} names={sections} roles={assignable_roles} />)}
            </div>
        </AppLayout>
    );
}
