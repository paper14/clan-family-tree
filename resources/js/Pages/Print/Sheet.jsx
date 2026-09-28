import { Head } from '@inertiajs/react';
import { useEffect } from 'react';
import { Button } from '../../design-system';
import { chartMetrics, ChartSheet, MARGIN_MM, OutlineSheet } from '../../Components/PrintSheet';

/** Read the print options from the URL (all strings). */
function readOptions(o) {
    const on = (k, d) => (o[k] == null ? d : o[k] === '1' || o[k] === 'true');
    return {
        format: o.format === 'outline' ? 'outline' : 'tree',
        numbering: o.numbering === 'relative' ? 'relative' : 'clan',
        w: Math.max(50, +o.w || 2438),
        h: Math.max(50, +o.h || 1219),
        fit: on('fit', true),
        dates: on('dates', true),
        photos: on('photos', false),
        headPhoto: on('headphoto', true),
        redact: on('redact', true),
        cousins: on('cousins', false),
        lastNames: on('lastnames', true),
        hidden: on('hidden', false),
        auto: on('auto', false),
    };
}

/** Print once the fonts and every image have loaded, so the PDF isn't missing anything. */
async function whenReady() {
    await document.fonts?.ready;
    await Promise.all(
        [...document.images].map((img) =>
            img.complete
                ? null
                : new Promise((res) => {
                      img.addEventListener('load', res, { once: true });
                      img.addEventListener('error', res, { once: true });
                  }),
        ),
    );
}

/**
 * The print route: only the sheet, data-theme="print" (set by the root view), and @page at the
 * chosen sheet size — Chrome and Edge honour it in "Save as PDF", including the 2438 × 1219 mm
 * tarpaulin (architecture-local.md §6).
 */
export default function PrintSheetPage({ data, options }) {
    const P = readOptions(options);
    const tree = P.format === 'tree';
    const m = tree && data.root ? chartMetrics(data, P) : null;
    const page = tree ? `@page { size: ${P.w}mm ${P.h}mm; margin: ${MARGIN_MM}mm; }` : '@page { size: 210mm 297mm; margin: 15mm; }';

    useEffect(() => {
        document.documentElement.setAttribute('data-theme', 'print');
        if (P.auto) whenReady().then(() => setTimeout(() => window.print(), 100));
    }, []);

    return (
        <div className="print-page">
            <Head title="Print" />
            <style>{`${page} html, body { background: var(--paper); } body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }`}</style>
            <div className="print-toolbar">
                <span className="small" style={{ alignSelf: 'center', background: 'var(--paper)', padding: '4px 8px' }}>
                    Choose “Save as PDF”, and turn on “Background graphics”.
                </span>
                <Button variant="primary" onClick={() => window.print()}>
                    Print or save as PDF
                </Button>
            </div>
            {!data.root ? (
                <p>Nothing to print.</p>
            ) : tree ? (
                <div style={{ position: 'relative', width: m.chartW * m.scale, height: m.chartH * m.scale }}>
                    <div style={{ position: 'absolute', left: 0, top: 0, transform: `scale(${m.scale})`, transformOrigin: '0 0' }}>
                        <ChartSheet data={data} P={P} m={m} />
                    </div>
                </div>
            ) : (
                <OutlineSheet data={data} P={P} />
            )}
        </div>
    );
}

PrintSheetPage.layout = (page) => page;
