import { Head, Link, useForm } from '@inertiajs/react';
import { Button, Field } from '../../design-system';
import { ErrorNote, Radios } from '../../Components/ui';

/** Add clan: a name is enough; the founding couple can be typed in now or set later. */
export default function ClanCreate() {
    const form = useForm({ name: '', origin_place: '', notes: '', f_given: '', f_last: '', s_given: '', s_last: '', f_sex: 'male' });
    const set = (k) => (e) => form.setData(k, e.target.value);
    const submit = (e) => {
        e.preventDefault();
        form.post('/clans');
    };

    return (
        <form className="stack" style={{ maxWidth: 720, gap: 24 }} onSubmit={submit} noValidate>
            <Head title="Add clan" />
            <header className="stack" style={{ gap: 4 }}>
                <Link className="small" href="/">
                    ‹ Clans
                </Link>
                <h1 className="display">Add clan</h1>
                <p className="muted" style={{ margin: 0 }}>
                    A clan starts with a name. The founding couple can be typed in now or set later.
                </p>
            </header>
            <ErrorNote errors={form.errors} />
            <section className="card">
                <div className="grid2">
                    <Field label="Clan name" required value={form.data.name} onChange={set('name')} placeholder="The founding surname, or the name the family uses" className={form.errors.name ? 'cl-field-error' : ''} />
                    <Field label="Origin place" value={form.data.origin_place} onChange={set('origin_place')} />
                </div>
                <Field label="Notes" multiline value={form.data.notes} onChange={set('notes')} placeholder="Oral history, where the records came from" />
            </section>
            <section className="card">
                <h2>Founding couple (Gen 1)</h2>
                <p className="small muted" style={{ margin: 0 }}>
                    One name each is enough. Leave blank to add them later from Clan settings.
                </p>
                <div className="grid2">
                    <Field label="Founder — given name" value={form.data.f_given} onChange={set('f_given')} />
                    <Field label="Last name" value={form.data.f_last} onChange={set('f_last')} />
                    <Field label="Founder’s spouse — given name" value={form.data.s_given} onChange={set('s_given')} />
                    <Field label="Last name" value={form.data.s_last} onChange={set('s_last')} />
                </div>
                <Radios name="f_sex" legend="Founder is" value={form.data.f_sex} onChange={(v) => form.setData('f_sex', v)} options={[['male', 'Male'], ['female', 'Female']]} />
            </section>
            <div className="row">
                <span style={{ flexGrow: 1 }} />
                <Link className="cl-btn" href="/">
                    Cancel
                </Link>
                <Button type="submit" variant="primary" disabled={form.processing}>
                    Create clan
                </Button>
            </div>
        </form>
    );
}
