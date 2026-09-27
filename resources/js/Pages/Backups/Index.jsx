import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { Badge, Button } from '../../design-system';
import { ErrorNote } from '../../Components/ui';

function size(bytes) {
    if (bytes > 1024 * 1024) return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
    return `${Math.max(1, Math.round(bytes / 1024))} KB`;
}

const TAGS = {
    'before-renumbering': 'Before Set founding couple',
    'before-migrate': 'Before a migration',
    'before-restore': 'Safety backup before a restore',
};

/** Backup and restore (docs/architecture-local.md §4). */
export default function BackupsIndex({ folder, backups, keepAuto }) {
    const { errors } = usePage().props;
    const [busy, setBusy] = useState(null);

    const restore = (b) => {
        const when = new Date(b.created_at).toLocaleString();
        const counts = b.readable ? `${b.clans} clans, ${b.people} people${b.photos ? `, ${b.photos} photos` : ''}` : 'counts unknown';
        if (!window.confirm(`Restore the backup from ${when} (${counts})?\n\nThis replaces everything in the registry and the photos folder. A safety backup of the current state is taken first.`)) return;
        setBusy(b.file);
        router.post('/backups/restore', { file: b.file }, { onFinish: () => setBusy(null) });
    };

    const backupNow = () => {
        setBusy('new');
        router.post('/backups', {}, { preserveScroll: true, onFinish: () => setBusy(null) });
    };

    return (
        <div className="stack" style={{ maxWidth: 860, gap: 24 }}>
            <Head title="Backups" />
            <div className="page-head" style={{ marginBottom: 0 }}>
                <div className="grow">
                    <h1 className="display">Backups</h1>
                    <p className="muted" style={{ margin: 0 }}>
                        Each backup is one zip: the whole database (all clans) and the photos folder. Keep a copy off this computer too.
                    </p>
                </div>
                <Button variant="primary" onClick={backupNow} disabled={!!busy}>
                    {busy === 'new' ? 'Backing up…' : 'Back up now'}
                </Button>
            </div>
            <ErrorNote errors={errors} keys={['restore', 'backup']} />
            <section className="card">
                <h2>Backup folder</h2>
                {folder ? <div className="mono">{folder}</div> : <div className="note warn"><b>No backup folder set.</b> Put CLAN_BACKUP_PATH in the .env file.</div>}
                <p className="small muted" style={{ margin: 0 }}>
                    Set in the .env file (CLAN_BACKUP_PATH). Best on another drive or a synced folder — a backup that lives only next to the thing it
                    protects is not a backup. Backups are also taken automatically before Set founding couple, before migrations and before a restore; the
                    last {keepAuto} automatic ones are kept. Backups you take yourself are never deleted.
                </p>
            </section>
            <section className="card">
                <h2>Restore</h2>
                <p className="small muted" style={{ margin: 0 }}>
                    Restoring replaces the whole registry and the photos folder with the backup’s, then renumbers every clan. A safety backup of the current
                    state is taken first. To restore a backup kept elsewhere, copy its zip into the backup folder.
                </p>
                {backups.length ? (
                    <div>
                        {backups.map((b) => (
                            <div key={b.file} className="bk-row">
                                <div style={{ minWidth: 0 }}>
                                    <div style={{ fontWeight: 600 }}>
                                        {new Date(b.created_at).toLocaleString()}{' '}
                                        {b.auto ? <Badge>{TAGS[b.tag] || 'Automatic'}</Badge> : <Badge tone="lineage">Taken by hand</Badge>}
                                    </div>
                                    <div className="small muted">
                                        {b.readable ? `${b.clans} clans · ${b.people} people · ${b.photos} photos · ` : 'Not readable · '}
                                        {size(b.size)} · <span className="mono">{b.file}</span>
                                    </div>
                                </div>
                                <Button className="cl-btn-sm" onClick={() => restore(b)} disabled={!!busy || !b.readable}>
                                    {busy === b.file ? 'Restoring…' : 'Restore'}
                                </Button>
                            </div>
                        ))}
                    </div>
                ) : (
                    <p className="small muted" style={{ margin: 0 }}>
                        No backups yet.
                    </p>
                )}
            </section>
        </div>
    );
}
