export function GitConnectionSteps({ current }: { current: 0 | 1 | 2 }) {
    return <ol aria-label="مراحل اتصال حساب" className="my-5 grid grid-cols-3 gap-2">
        {['انتخاب سرویس', 'تأیید حساب', 'انتخاب مخزن‌ها'].map((label, index) => <li key={label} aria-current={index === current ? 'step' : undefined} className={`min-w-0 border-t-2 pt-3 text-center text-xs leading-6 ${index <= current ? 'border-accent text-accent' : 'border-line text-muted'}`}><span className="me-1">{(index + 1).toLocaleString('fa-IR')}.</span>{label}</li>)}
    </ol>;
}
