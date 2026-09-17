import { type FormEvent } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    BookOpenText,
    ChevronDown,
    CircleHelp,
    FileJson,
    ShieldCheck,
} from 'lucide-react';
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
        signature_verified_by_observatory: boolean;
        verified_submission_claims: Record<string, unknown>;
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

type ClaimGuide = {
    label: string;
    summary: string;
    reading: string;
    kind?: 'time';
};

const claimGuides: Record<string, ClaimGuide> = {
    aud: {
        label: 'Tujuan token',
        summary:
            'Menentukan layanan yang boleh menerima token. Nilainya harus cocok dengan layanan tujuan.',
        reading:
            'Nilai api.tailscale.com berarti token ini diminta untuk proses autentikasi ke Tailscale.',
    },
    event_name: {
        label: 'Cara workflow dipicu',
        summary: 'Menunjukkan kejadian yang memulai GitHub Actions.',
        reading:
            'workflow_dispatch berarti percobaan dijalankan melalui tombol, API, atau antarmuka eksperimen; bukan otomatis karena push.',
    },
    exp: {
        label: 'Waktu token kedaluwarsa',
        summary: 'Setelah waktu ini token tidak boleh diterima lagi.',
        reading:
            'Bandingkan dengan waktu penerbitan. Selisih keduanya menunjukkan masa berlaku token.',
        kind: 'time',
    },
    iat: {
        label: 'Waktu token diterbitkan',
        summary: 'Waktu ketika GitHub membuat token OIDC.',
        reading: 'Token mulai dihitung masa berlakunya dari waktu ini.',
        kind: 'time',
    },
    iss: {
        label: 'Penerbit identitas',
        summary: 'Pihak yang membuat dan menandatangani token OIDC.',
        reading:
            'token.actions.githubusercontent.com menunjukkan bahwa identitas diterbitkan oleh GitHub Actions.',
    },
    job_workflow_ref: {
        label: 'Workflow asal pekerjaan',
        summary:
            'Lokasi file workflow dan branch atau commit yang menjalankan pekerjaan.',
        reading:
            'Baca dari kiri: repositori, lokasi file workflow, lalu bagian setelah @ adalah branch atau commit.',
    },
    jti_sha256: {
        label: 'Sidik jari token',
        summary:
            'Hash dari ID unik token untuk kebutuhan audit tanpa menyimpan ID atau token mentah.',
        reading:
            'Nilai panjang ini bukan password. Gunakan untuk membedakan satu penerbitan token dari token lainnya.',
    },
    nbf: {
        label: 'Mulai berlaku',
        summary: 'Token tidak boleh digunakan sebelum waktu ini.',
        reading:
            'Pemeriksa memastikan waktu server sudah melewati nilai ini sebelum menerima token.',
        kind: 'time',
    },
    ref: {
        label: 'Branch atau referensi Git',
        summary: 'Branch atau tag yang menjalankan workflow.',
        reading:
            'refs/heads/codex/wif-poc berarti workflow dijalankan dari branch codex/wif-poc.',
    },
    repository: {
        label: 'Nama repositori',
        summary: 'Repositori GitHub yang meminta identitas.',
        reading:
            'Formatnya adalah pemilik/repositori, misalnya msaririzki/sita.',
    },
    repository_id: {
        label: 'ID permanen repositori',
        summary:
            'Nomor unik dari GitHub yang tetap mengidentifikasi repositori walaupun namanya berubah.',
        reading:
            'Kebijakan dapat memeriksa nomor ini untuk mencegah repositori lain memakai nama yang mirip.',
    },
    repository_owner_id: {
        label: 'ID permanen pemilik',
        summary: 'Nomor unik akun atau organisasi pemilik repositori.',
        reading:
            'Nomor ini membuktikan pemilik sebenarnya, bukan hanya mencocokkan nama akun.',
    },
    sub: {
        label: 'Identitas utama peminta',
        summary:
            'Rangkuman subjek yang sedang meminta akses, dibentuk dari repositori dan konteks Git.',
        reading:
            'Baca repo sebagai sumber identitas dan ref sebagai branch yang menjalankan pekerjaan.',
    },
    workflow_ref: {
        label: 'Workflow yang dijalankan',
        summary:
            'File GitHub Actions beserta branch atau commit yang digunakan pada percobaan.',
        reading:
            'Nilai ini membantu memastikan hanya workflow yang disetujui yang dapat meminta akses.',
    },
    environment: {
        label: 'Lingkungan GitHub',
        summary:
            'Nama GitHub Environment yang digunakan, misalnya production atau staging.',
        reading:
            'Nilai ini dapat dijadikan syarat tambahan pada kebijakan WIF multi-klaim.',
    },
};

function claimGuide(key: string): ClaimGuide {
    return (
        claimGuides[key] ?? {
            label: key,
            summary: 'Data teknis tambahan yang diterbitkan bersama identitas.',
            reading: 'Nilai asli dipertahankan sebagai bukti audit.',
        }
    );
}

function formatUnixTime(value: string | number | null): string | null {
    const seconds = Number(value);
    if (!Number.isFinite(seconds)) {
        return null;
    }

    return new Intl.DateTimeFormat('id-ID', {
        dateStyle: 'long',
        timeStyle: 'long',
        timeZone: 'Asia/Makassar',
    }).format(new Date(seconds * 1000));
}
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
                    Bukti dapat dikirim otomatis oleh GitHub Actions menggunakan
                    token OIDC khusus atau diimpor operator sebagai cadangan.
                    Status verifikasi setiap percobaan ditampilkan di bawah. Ini
                    masih uji konektivitas WIF, belum deployment aplikasi atau
                    perbandingan statistik tiga metode. Token mentah tidak
                    disimpan.
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
                                            Sumber:{' '}
                                            <strong>
                                                {trial.metadata.source ===
                                                'oidc_authenticated_workflow_push'
                                                    ? 'GitHub Actions otomatis'
                                                    : 'Impor operator'}
                                            </strong>
                                        </span>
                                        <span>
                                            Tanda tangan JWT:{' '}
                                            <strong>
                                                {trial.metadata
                                                    .signature_verified_by_observatory
                                                    ? 'Terverifikasi'
                                                    : 'Belum diverifikasi'}
                                            </strong>
                                        </span>
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
                                            Identitas yang dibuktikan GitHub
                                        </h2>
                                        <div className="bg-muted/30 flex gap-3 rounded-xl border p-4 text-sm leading-6">
                                            <BookOpenText
                                                className="mt-1 size-4 shrink-0"
                                                aria-hidden="true"
                                            />
                                            <div>
                                                <p className="font-medium">
                                                    Cara membaca bagian ini
                                                </p>
                                                <p className="text-muted-foreground">
                                                    Setiap baris adalah satu
                                                    pernyataan identitas dari
                                                    GitHub. Nama yang mudah
                                                    dipahami ditampilkan lebih
                                                    dahulu, sedangkan kode asli
                                                    seperti{' '}
                                                    <code translate="no">
                                                        aud
                                                    </code>{' '}
                                                    tetap disimpan untuk bukti
                                                    teknis. Tekan ikon bantuan
                                                    atau barisnya untuk melihat
                                                    penjelasan dan cara membaca
                                                    nilainya.
                                                </p>
                                            </div>
                                        </div>
                                        <div className="grid gap-3">
                                            {Object.entries(
                                                trial.metadata.evidence
                                                    .oidc_claims ?? {},
                                            ).map(([key, value]) => {
                                                const guide = claimGuide(key);
                                                const readableTime =
                                                    guide.kind === 'time'
                                                        ? formatUnixTime(value)
                                                        : null;

                                                return (
                                                    <details
                                                        key={key}
                                                        className="group rounded-xl border"
                                                    >
                                                        <summary className="flex min-h-14 cursor-pointer list-none items-center gap-3 rounded-xl p-4 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 [&::-webkit-details-marker]:hidden">
                                                            <CircleHelp
                                                                className="size-5 shrink-0 text-indigo-600"
                                                                aria-hidden="true"
                                                            />
                                                            <div className="min-w-0 flex-1">
                                                                <div className="flex flex-wrap items-baseline gap-x-2 gap-y-1">
                                                                    <span className="font-medium">
                                                                        {
                                                                            guide.label
                                                                        }
                                                                    </span>
                                                                    <code
                                                                        className="text-muted-foreground text-xs"
                                                                        translate="no"
                                                                    >
                                                                        {key}
                                                                    </code>
                                                                </div>
                                                                <p className="text-muted-foreground mt-1 text-sm">
                                                                    {
                                                                        guide.summary
                                                                    }
                                                                </p>
                                                            </div>
                                                            <ChevronDown
                                                                className="size-4 shrink-0 transition-transform group-open:rotate-180"
                                                                aria-hidden="true"
                                                            />
                                                        </summary>
                                                        <div className="space-y-3 border-t px-4 pt-3 pb-4 sm:pl-12">
                                                            {readableTime && (
                                                                <p className="text-sm">
                                                                    <span className="text-muted-foreground">
                                                                        Waktu
                                                                        yang
                                                                        mudah
                                                                        dibaca:{' '}
                                                                    </span>
                                                                    <strong>
                                                                        {
                                                                            readableTime
                                                                        }
                                                                    </strong>{' '}
                                                                    <span className="text-muted-foreground">
                                                                        (WITA)
                                                                    </span>
                                                                </p>
                                                            )}
                                                            <p className="text-sm">
                                                                <span className="text-muted-foreground">
                                                                    Cara
                                                                    baca:{' '}
                                                                </span>
                                                                {guide.reading}
                                                            </p>
                                                            <div>
                                                                <p className="text-muted-foreground mb-1 text-xs">
                                                                    Nilai teknis
                                                                    asli
                                                                </p>
                                                                <code
                                                                    className="bg-muted block overflow-x-auto rounded-lg p-3 text-xs break-all whitespace-pre-wrap"
                                                                    translate="no"
                                                                >
                                                                    {String(
                                                                        value ??
                                                                            'Tidak tersedia',
                                                                    )}
                                                                </code>
                                                            </div>
                                                        </div>
                                                    </details>
                                                );
                                            })}
                                        </div>
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
