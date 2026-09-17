import { type FormEvent } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, FileJson, ShieldCheck } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Stage = {
    name: string;
    status: string;
    duration_ms: string | number | null;
    occurred_at: string;
};
type Evidence = {
    github: {
        repository: string;
        sha: string;
        ref: string;
        workflow_ref: string;
        run_id: string;
    };
    oidc_claims: Record<string, string | number | null> | null;
    tailscale: {
        backend_state?: string;
        node_id?: string;
        tags?: string[];
    } | null;
    integrity: {
        generated_at: string;
        collector_version: string;
        policy_version: string | null;
    };
};
type Trial = {
    id: string;
    sequence: number;
    status: string;
    run_id: number | null;
    run_attempt: number | null;
    expected_decision: string;
    actual_decision: string | null;
    classification: string | null;
    network_path: string | null;
    authentication_ms: string | number | null;
    reachability_ms: string | number | null;
    ssh_ms: string | number | null;
    total_ms: string | number | null;
    metadata: {
        source: string;
        evidence_sha256: string;
        measurement_valid: boolean;
        evidence: Evidence;
    } | null;
    stages: Stage[];
};
type Props = {
    experiment: {
        id: string;
        name: string;
        profile: string;
        scenario: string;
        status: string;
        git_ref: string;
        target: string;
    };
    trials: Trial[];
};
const stageLabels: Record<string, string> = {
    preflight: 'Validasi rancangan dan konfigurasi',
    oidc_claim_capture: 'Pengambilan klaim OIDC',
    wif_exchange_and_join: 'Autentikasi WIF + bergabung ke tailnet',
    target_reachability: 'Keterjangkauan VM privat',
    tailscale_ssh: 'Akses Tailscale SSH + versi Docker',
};
function duration(value: string | number | null): string {
    return value === null
        ? 'Tidak diukur'
        : `${Number(value).toLocaleString('id-ID', { maximumFractionDigits: 3 })} ms`;
}

export default function ExperimentEvidence({ experiment, trials }: Props) {
    const form = useForm<{ evidence_file: File | null }>({
        evidence_file: null,
    });
    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.post(`/experiments/${experiment.id}/evidence`, {
            forceFormData: true,
            preserveScroll: true,
        });
    }
    return (
        <>
            <Head title={`Bukti: ${experiment.name}`} />
            <div className="mx-auto w-full max-w-6xl space-y-6 p-4 lg:p-6">
                <Button variant="ghost" asChild>
                    <Link href="/experiments">
                        <ArrowLeft /> Kembali ke eksperimen
                    </Link>
                </Button>
                <header className="space-y-2">
                    <p className="text-sm font-medium text-indigo-600">
                        Bukti autentikasi · Pilot fungsional
                    </p>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        {experiment.name}
                    </h1>
                    <p className="text-muted-foreground text-sm">
                        {experiment.profile} · {experiment.scenario} ·{' '}
                        {experiment.target} · {experiment.git_ref}
                    </p>
                    <Badge variant="outline">{experiment.status}</Badge>
                </header>
                <div className="bg-muted/30 rounded-xl border p-4 text-sm leading-6">
                    Data berasal dari artefak workflow GitHub Actions yang
                    diimpor operator. Ini uji konektivitas WIF, belum deployment
                    aplikasi atau perbandingan statistik tiga metode. Waktu WIF
                    mencakup persiapan action, autentikasi, dan bergabung ke
                    tailnet. Jumlah waktu hanya menjumlahkan tahap yang diukur.
                    Klaim yang ditampilkan dibaca dari token; Observatory belum
                    memverifikasi tanda tangan JWT secara independen. Token
                    mentah tidak disimpan.
                </div>
                {trials.map((trial) => (
                    <Card key={trial.id} className="shadow-none">
                        <CardHeader>
                            <div className="flex flex-wrap items-center justify-between gap-3">
                                <CardTitle>
                                    Percobaan #{trial.sequence}
                                </CardTitle>
                                <div className="flex gap-2">
                                    <Badge variant="outline">
                                        {trial.status}
                                    </Badge>
                                    {trial.classification && (
                                        <Badge>{trial.classification}</Badge>
                                    )}
                                </div>
                            </div>
                            <CardDescription className="break-all">
                                Trial ID: {trial.id}
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-6">
                            {!trial.metadata ? (
                                <p className="text-muted-foreground text-sm">
                                    Belum ada bukti. Tidak ada angka simulasi
                                    yang ditampilkan.
                                </p>
                            ) : (
                                <>
                                    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                                        {[
                                            [
                                                'WIF + bergabung',
                                                duration(
                                                    trial.authentication_ms,
                                                ),
                                            ],
                                            [
                                                'Keterjangkauan',
                                                duration(trial.reachability_ms),
                                            ],
                                            [
                                                'SSH + Docker',
                                                duration(trial.ssh_ms),
                                            ],
                                            [
                                                'Jumlah tahap diukur',
                                                duration(trial.total_ms),
                                            ],
                                        ].map(([label, value]) => (
                                            <div
                                                key={label}
                                                className="rounded-xl border p-4"
                                            >
                                                <p className="text-muted-foreground text-xs">
                                                    {label}
                                                </p>
                                                <p className="mt-2 text-lg font-semibold tabular-nums">
                                                    {value}
                                                </p>
                                            </div>
                                        ))}
                                    </div>
                                    <div className="flex flex-wrap gap-4 text-sm">
                                        <span>
                                            Harapan:{' '}
                                            <strong>
                                                {trial.expected_decision}
                                            </strong>
                                        </span>
                                        <span>
                                            Aktual akses:{' '}
                                            <strong>
                                                {trial.actual_decision}
                                            </strong>
                                        </span>
                                        <span>
                                            Jalur:{' '}
                                            <strong>
                                                {trial.network_path}
                                            </strong>
                                        </span>
                                        <span>
                                            Layak dianalisis:{' '}
                                            <strong>
                                                {trial.metadata
                                                    .measurement_valid
                                                    ? 'Ya, sebagai pilot'
                                                    : 'Tidak, kesalahan instrumen'}
                                            </strong>
                                        </span>
                                    </div>
                                    <div className="overflow-x-auto rounded-xl border">
                                        <table className="w-full text-left text-sm">
                                            <thead className="bg-muted/40">
                                                <tr>
                                                    <th className="p-3">
                                                        Tahap
                                                    </th>
                                                    <th className="p-3">
                                                        Hasil
                                                    </th>
                                                    <th className="p-3">
                                                        Durasi
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {trial.stages.map((stage) => (
                                                    <tr
                                                        key={stage.name}
                                                        className="border-t"
                                                    >
                                                        <td className="p-3">
                                                            {stageLabels[
                                                                stage.name
                                                            ] ?? stage.name}
                                                        </td>
                                                        <td className="p-3">
                                                            <Badge variant="outline">
                                                                {stage.status}
                                                            </Badge>
                                                        </td>
                                                        <td className="p-3 tabular-nums">
                                                            {duration(
                                                                stage.duration_ms,
                                                            )}
                                                        </td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                    <section className="space-y-3">
                                        <h2 className="flex items-center gap-2 font-medium">
                                            <ShieldCheck className="size-4" />{' '}
                                            Klaim OIDC yang dikumpulkan
                                        </h2>
                                        <dl className="grid gap-2 rounded-xl border p-4">
                                            {Object.entries(
                                                trial.metadata.evidence
                                                    .oidc_claims ?? {},
                                            ).map(([key, value]) => (
                                                <div
                                                    key={key}
                                                    className="grid gap-1 sm:grid-cols-[10rem_1fr]"
                                                >
                                                    <dt className="text-muted-foreground font-mono text-xs">
                                                        {key}
                                                    </dt>
                                                    <dd className="font-mono text-xs break-all">
                                                        {String(
                                                            value ??
                                                                'Tidak tersedia',
                                                        )}
                                                    </dd>
                                                </div>
                                            ))}
                                        </dl>
                                    </section>
                                    <div className="space-y-2 rounded-xl border p-4 text-xs">
                                        <p className="break-all">
                                            SHA commit:{' '}
                                            <code>
                                                {
                                                    trial.metadata.evidence
                                                        .github.sha
                                                }
                                            </code>
                                        </p>
                                        <p className="break-all">
                                            SHA-256 bukti tersanitasi:{' '}
                                            <code>
                                                {trial.metadata.evidence_sha256}
                                            </code>
                                        </p>
                                        <p>
                                            Collector:{' '}
                                            {
                                                trial.metadata.evidence
                                                    .integrity.collector_version
                                            }{' '}
                                            · Kebijakan:{' '}
                                            {
                                                trial.metadata.evidence
                                                    .integrity.policy_version
                                            }
                                        </p>
                                        <p>
                                            Dibuat:{' '}
                                            {
                                                trial.metadata.evidence
                                                    .integrity.generated_at
                                            }
                                        </p>
                                        <a
                                            className="font-medium text-indigo-600 underline"
                                            href={`https://github.com/${trial.metadata.evidence.github.repository}/actions/runs/${trial.metadata.evidence.github.run_id}`}
                                            target="_blank"
                                            rel="noopener noreferrer"
                                        >
                                            Buka run sumber #{trial.run_id} ·
                                            attempt {trial.run_attempt}
                                        </a>
                                    </div>
                                    <details className="rounded-xl border p-4">
                                        <summary className="cursor-pointer text-sm font-medium">
                                            JSON bukti tersanitasi lengkap
                                        </summary>
                                        <pre className="mt-3 max-h-96 overflow-auto text-xs">
                                            {JSON.stringify(
                                                trial.metadata.evidence,
                                                null,
                                                2,
                                            )}
                                        </pre>
                                    </details>
                                </>
                            )}
                        </CardContent>
                    </Card>
                ))}
                <Card className="shadow-none">
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <FileJson className="size-4" /> Impor bukti pilot
                        </CardTitle>
                        <CardDescription>
                            Unggah trial-evidence.json dari artefak run. ID,
                            ref, SHA, keputusan, dan struktur diperiksa. Bukti
                            lama tidak ditimpa.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="space-y-3">
                            <Label htmlFor="evidence_file">
                                Berkas JSON, maksimum 64 KiB
                            </Label>
                            <Input
                                id="evidence_file"
                                type="file"
                                accept=".json,application/json"
                                onChange={(event) =>
                                    form.setData(
                                        'evidence_file',
                                        event.target.files?.[0] ?? null,
                                    )
                                }
                            />
                            {Object.values(form.errors).map((error) => (
                                <p
                                    key={error}
                                    className="text-destructive text-sm"
                                >
                                    {error}
                                </p>
                            ))}
                            <Button
                                disabled={
                                    !form.data.evidence_file || form.processing
                                }
                            >
                                Validasi dan simpan bukti
                            </Button>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
ExperimentEvidence.layout = {
    breadcrumbs: [
        { title: 'Experiments', href: '/experiments' },
        { title: 'Bukti pilot', href: '#' },
    ],
};
