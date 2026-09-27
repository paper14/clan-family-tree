import { Head, Link } from '@inertiajs/react';

export default function NeedClan() {
    return (
        <>
            <Head title="Choose a clan" />
            <h1 className="display">Choose a clan</h1>
            <p className="muted">
                Everything is scoped to one clan. <Link href="/">Go to the clan list</Link>.
            </p>
        </>
    );
}
