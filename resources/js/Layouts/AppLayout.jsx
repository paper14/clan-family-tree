import { Link, router, usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { Badge, Button } from '../design-system';

/** Which kind of screen we're on, for the nav highlight and for staying put when the clan changes. */
function screenOf(component, props) {
    if (component === 'People/Form') return props.isNew ? 'new' : 'people';
    return (
        {
            'Clans/Index': 'clans', 'Clans/Create': 'clans', 'Clans/Settings': 'settings',
            'People/Index': 'people', 'People/Show': 'people', 'Children/Create': 'children',
            'Tree/Index': 'tree', 'Print/Index': 'print', 'Backups/Index': 'backups',
        }[component] || ''
    );
}

function Toast() {
    const { flash } = usePage().props;
    const [msg, setMsg] = useState(null);
    const [on, setOn] = useState(false);
    const timer = useRef();

    const show = (m) => {
        if (!m) return;
        setMsg(m);
        setOn(true);
        clearTimeout(timer.current);
        timer.current = setTimeout(() => setOn(false), 4200);
    };

    useEffect(() => show(flash?.toast), [flash?.toastId]);
    useEffect(() => {
        const h = (e) => show(e.detail);
        window.addEventListener('clan-toast', h);
        return () => window.removeEventListener('clan-toast', h);
    }, []);

    return (
        <div className={`toast ${on ? 'on' : ''}`} role="status" aria-live="polite">
            {msg}
        </div>
    );
}

function when(iso) {
    if (!iso) return null;
    const d = new Date(iso);
    return d.toLocaleString(undefined, { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

export default function AppLayout({ children }) {
    const page = usePage();
    const { app, errors } = page.props;
    const screen = screenOf(page.component, page.props);
    const c = app.currentClan;
    const [theme, setTheme] = useState(() => document.documentElement.getAttribute('data-theme') || 'paper');
    const [backingUp, setBackingUp] = useState(false);

    const toggleTheme = () => {
        const next = theme === 'lamplight' ? 'paper' : 'lamplight';
        document.documentElement.setAttribute('data-theme', next);
        document.cookie = `theme=${next}; path=/; max-age=31536000; SameSite=Lax`;
        setTheme(next);
    };

    const pickClan = (id) => {
        if (!id) return;
        router.post('/current-clan', { clan_id: id, to: screen === 'clans' || screen === 'backups' ? 'open' : screen });
    };

    const backup = () => {
        setBackingUp(true);
        router.post('/backups', {}, { preserveScroll: true, onFinish: () => setBackingUp(false) });
    };

    const nav = [['clans', 'All clans', '/']];
    const scoped = c
        ? [
              ['people', 'People', '/people'],
              ['new', 'Add person', '/people/create'],
              ['children', 'Add children', '/children'],
              ['tree', 'Tree / Outline', '/tree'],
              ['print', 'Print', '/print'],
              ['settings', 'Clan settings', `/clans/${c.id}/settings`],
          ]
        : [];

    const link = ([key, label, href]) => (
        <Link key={key} href={href} className={screen === key ? 'on' : ''} aria-current={screen === key ? 'page' : undefined}>
            <span>{label}</span>
            {key === 'people' && c?.unplaced ? <Badge tone="warn">{c.unplaced}</Badge> : null}
        </Link>
    );

    return (
        <div className="app">
            <nav className="side" aria-label="Main">
                <div className="brand">
                    <div className="t">Clan Family Tree</div>
                    <div className="small muted">
                        {app.totals.clans} {app.totals.clans === 1 ? 'clan' : 'clans'} · {app.totals.people} people
                    </div>
                </div>
                {!app.isRegistry && (
                    <div className="demo-flag">
                        <b>Demo data</b> — the sample clans in {app.database}, not the family registry.
                    </div>
                )}
                {app.clans.length > 0 && (
                    <div className="clan-pick">
                        <label className="small muted" htmlFor="clan-sel">
                            Clan
                        </label>
                        <select className="cl-input" id="clan-sel" value={c?.id ?? ''} onChange={(e) => pickClan(e.target.value)}>
                            {!c && <option value="">— Choose —</option>}
                            {app.clans.map((x) => (
                                <option key={x.id} value={x.id}>
                                    {x.label}
                                </option>
                            ))}
                        </select>
                    </div>
                )}
                <div className="nav">
                    {nav.map(link)}
                    {scoped.length > 0 && <div className="sep" />}
                    {scoped.map(link)}
                </div>
                <div className="grow" />
                <div className="box">
                    <div className="small" style={{ fontWeight: 600 }}>
                        Backup · all clans
                    </div>
                    <div className="small muted">{app.lastBackup ? `Last: ${when(app.lastBackup)}` : 'Never backed up'}</div>
                    {errors?.backup && <div className="small" style={{ color: 'var(--danger)' }}>{errors.backup}</div>}
                    <Button className="cl-btn-sm" onClick={backup} disabled={backingUp}>
                        {backingUp ? 'Backing up…' : 'Back up now'}
                    </Button>
                    <Link className="cl-btn cl-btn-link cl-btn-sm" href="/backups">
                        Backups and restore
                    </Link>
                </div>
                <div className="row" style={{ padding: '0 4px' }}>
                    <Button className="cl-btn-sm" onClick={toggleTheme}>
                        {theme === 'lamplight' ? 'Light theme' : 'Dark theme'}
                    </Button>
                </div>
            </nav>
            <main id="main" tabIndex={-1}>
                {children}
            </main>
            <Toast />
        </div>
    );
}
