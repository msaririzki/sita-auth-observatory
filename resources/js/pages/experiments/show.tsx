import { type FormEvent, useState } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
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
    preflight: 'Memastikan pengaturan uji sudah siap',
    oidc_claim_capture: 'Membaca identitas proses dari GitHub',
    wif_exchange_and_join: 'Meminta akses privat lewat Tailscale',
    target_reachability: 'Memastikan server SITA dapat dihubungi',
    tailscale_ssh: 'Masuk ke server dan memeriksa Docker',
    docker_deployment: 'Memperbarui kandidat aplikasi SITA',
    application_healthcheck: 'Memastikan aplikasi SITA dapat dibuka',
};

const profileLabels: Record<string, string> = {
    oauth_static: 'OAuth statis',
    wif_basic: 'WIF dasar',
    wif_multi_claim: 'WIF dengan pembatasan tambahan',
};

const scenarioLabels: Record<string, string> = {
    valid: 'Akses yang seharusnya diizinkan',
    wrong_audience: 'Tujuan token yang salah',
};

const statusLabels: Record<string, string> = {
    pending: 'Menunggu',
    running: 'Berjalan',
    completed: 'Berhasil',
    failed: 'Gagal',
    cancelled: 'Dibatalkan',
};

const stageStatusLabels: Record<string, string> = {
    pass: 'Berhasil',
    fail: 'Gagal',
    skipped: 'Dilewati',
};

type ClaimGuide = {
    label: string;
    summary: string;
    reading: string;
    kind?: 'time';
};

type ClaimValue = string | number | null;

type ClaimSection = {
    id: string;
    label: string;
    description: string;
    keys: string[];
};

const claimSections: ClaimSection[] = [
    {
        id: 'issuer-and-target',
        label: '1. Penerbit dan tujuan token',
        description: 'Menjawab siapa yang membuat token dan untuk layanan apa.',
        keys: ['iss', 'aud'],
    },
    {
        id: 'requester-identity',
        label: '2. Identitas peminta akses',
        description: 'Menunjukkan repositori dan pemilik yang meminta akses.',
        keys: ['sub', 'repository', 'repository_id', 'repository_owner_id'],
    },
    {
        id: 'workflow-context',
        label: '3. Proses GitHub yang berjalan',
        description: 'Menunjukkan branch, workflow, dan cara proses dimulai.',
        keys: [
            'ref',
            'workflow_ref',
            'job_workflow_ref',
            'event_name',
            'environment',
        ],
    },
    {
        id: 'validity-window',
        label: '4. Masa berlaku token',
        description:
            'Menunjukkan kapan token dibuat, mulai berlaku, dan berakhir.',
        keys: ['iat', 'nbf', 'exp'],
    },
    {
        id: 'audit-trail',
        label: '5. Jejak audit',
        description:
            'Sidik jari aman untuk membedakan setiap penerbitan token.',
        keys: ['jti_sha256'],
    },
];

const claimGuides: Record<string, ClaimGuide> = {
    aud: {
        label: 'Token ini ditujukan ke mana?',
        summary: 'Menentukan layanan yang diperbolehkan menerima token ini.',
        reading:
            'Awalan api.tailscale.com menunjukkan bahwa token ditujukan kepada Tailscale. Bagian setelahnya adalah identitas tujuan yang dikonfigurasi pada Tailscale.',
    },
    event_name: {
        label: 'Bagaimana proses ini dimulai?',
        summary: 'Menunjukkan pemicu yang menjalankan GitHub Actions.',
        reading:
            'workflow_dispatch berarti proses dijalankan melalui tombol, API, atau antarmuka eksperimen.',
    },
    exp: {
        label: 'Kapan token berakhir?',
        summary: 'Setelah waktu ini token tidak dapat digunakan lagi.',
        reading:
            'Lihat waktu WITA yang ditampilkan. Selisih antara iat dan exp adalah lama token berlaku.',
        kind: 'time',
    },
    iat: {
        label: 'Kapan token dibuat?',
        summary: 'Waktu ketika GitHub Actions menerbitkan token OIDC.',
        reading: 'Angka Unix ini diterjemahkan ke waktu WITA di bawah.',
        kind: 'time',
    },
    iss: {
        label: 'Siapa yang menerbitkan token?',
        summary:
            'Menunjukkan pihak yang membuat dan menandatangani token OIDC.',
        reading:
            'Alamat token.actions.githubusercontent.com membuktikan bahwa penerbitnya adalah GitHub Actions.',
    },
    job_workflow_ref: {
        label: 'Dari workflow mana pekerjaan berasal?',
        summary:
            'Menunjukkan file workflow sumber beserta branch atau commit-nya.',
        reading:
            'Sebelum tanda @ adalah repositori dan lokasi file. Setelah tanda @ adalah branch atau commit yang digunakan.',
    },
    jti_sha256: {
        label: 'Apa sidik jari audit token ini?',
        summary:
            'Pencatat mengubah ID unik jti menjadi hash SHA-256 agar dapat diaudit tanpa menyimpan ID mentah.',
        reading:
            'Nilai panjang ini bukan kata sandi. Nilai yang berbeda berarti token diterbitkan pada kejadian yang berbeda.',
    },
    nbf: {
        label: 'Kapan token mulai boleh dipakai?',
        summary: 'Token akan ditolak apabila digunakan sebelum waktu ini.',
        reading:
            'Lihat waktu WITA yang ditampilkan. Waktu server harus sudah melewati nilai ini.',
        kind: 'time',
    },
    ref: {
        label: 'Branch mana yang menjalankan proses?',
        summary: 'Menunjukkan branch atau tag Git yang sedang digunakan.',
        reading:
            'refs/heads/ berarti branch. Pada bukti ini nama branch-nya adalah codex/wif-poc.',
    },
    repository: {
        label: 'Repositori mana yang meminta akses?',
        summary: 'Menunjukkan repositori GitHub yang menjalankan proses.',
        reading:
            'Bagian sebelum garis miring adalah pemilik. Bagian setelahnya adalah nama repositori.',
    },
    repository_id: {
        label: 'Apa ID tetap repositorinya?',
        summary: 'Nomor unik yang diberikan GitHub kepada repositori.',
        reading:
            'Nomor ini tetap dapat mengenali repositori walaupun nama repositori diubah.',
    },
    repository_owner_id: {
        label: 'Apa ID tetap pemilik repositori?',
        summary: 'Nomor unik akun atau organisasi pemilik repositori.',
        reading:
            'Nomor ini dipakai untuk memastikan pemilik yang benar walaupun nama akun berubah.',
    },
    sub: {
        label: 'Siapa yang meminta akses?',
        summary:
            'Identitas utama yang menggabungkan pemilik, repositori, dan branch peminta akses.',
        reading:
            'repo menunjukkan sumber identitas. ref menunjukkan branch yang menjalankan pekerjaan.',
    },
    workflow_ref: {
        label: 'Workflow mana yang dijalankan?',
        summary:
            'Menunjukkan file GitHub Actions beserta branch atau commit-nya.',
        reading:
            'Kebijakan dapat memakai nilai ini agar hanya file workflow yang disetujui boleh meminta akses.',
    },
    environment: {
        label: 'Lingkungan GitHub mana yang digunakan?',
        summary: 'Nama GitHub Environment, misalnya production atau staging.',
        reading:
            'Nilai ini dapat membatasi akses hanya untuk lingkungan yang disetujui.',
    },
};

function groupClaims(claims: Record<string, ClaimValue>) {
    const knownKeys = new Set(claimSections.flatMap((section) => section.keys));
    const sections = claimSections.map((section) => ({
        ...section,
        entries: section.keys
            .filter((key) => key in claims)
            .map((key) => [key, claims[key]] as [string, ClaimValue]),
    }));
    const otherEntries = Object.entries(claims).filter(
        ([key]) => !knownKeys.has(key),
    );

    if (otherEntries.length > 0) {
        sections.push({
            id: 'other-claims',
            label: '6. Data teknis tambahan',
            description: 'Klaim lain yang ikut diterbitkan bersama identitas.',
            keys: otherEntries.map(([key]) => key),
            entries: otherEntries,
        });
    }

    return sections.filter((section) => section.entries.length > 0);
}

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

function decisionLabel(value: string | null): string {
    if (value === 'allow') {
        return 'Diizinkan';
    }
    if (value === 'deny') {
        return 'Ditolak';
    }

    return 'Belum tersedia';
}

function networkPathLabel(value: string | null): string {
    const labels: Record<string, string> = {
        direct: 'Langsung',
        derp: 'Melalui relay Tailscale',
        unknown: 'Tidak diketahui',
    };

    return value === null ? 'Belum tersedia' : (labels[value] ?? value);
}

function classificationLabel(value: string | null): string | null {
    const labels: Record<string, string> = {
        TP: 'Sesuai harapan (TP)',
        TN: 'Sesuai harapan (TN)',
        FP: 'Akses seharusnya ditolak (FP)',
        FN: 'Akses seharusnya diizinkan (FN)',
    };

    return value === null ? null : (labels[value] ?? value);
}

export default function ExperimentEvidence({ experiment, trials }: Props) {
    const [claimView, setClaimView] = useState<'technical' | 'guided'>(
        'technical',
    );
    const [explainedClaim, setExplainedClaim] = useState<string | null>(null);
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
                        Bukti hasil uji · Akses privat SITA
                    </p>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        {experiment.name}
                    </h1>
                    <div className="text-muted-foreground flex flex-wrap gap-x-3 gap-y-1 text-sm">
                        <span>
                            Cara akses: {profileLabels[experiment.profile] ?? experiment.profile}
                        </span>
                        <span>
                            Kondisi: {scenarioLabels[experiment.scenario] ?? experiment.scenario}
                        </span>
                        <span>Server: {experiment.target}</span>
                        <span>Branch: {experiment.git_ref}</span>
                    </div>
                    <Badge variant="outline">
                        {statusLabels[experiment.status] ?? experiment.status}
                    </Badge>
                </header>
                <div className="bg-muted/30 rounded-xl border p-4 text-sm leading-6">
                    Halaman ini menjawab tiga hal: apakah GitHub membuktikan
                    identitasnya, apakah akses privat ke server SITA berhasil,
                    dan apakah kandidat aplikasi dapat berjalan. Nilai teknis
                    tetap tersimpan untuk audit, tetapi token asli tidak
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
                                        {statusLabels[trial.status] ??
                                            trial.status}
                                    </Badge>
                                    {trial.classification && (
                                        <Badge>
                                            {classificationLabel(
                                                trial.classification,
                                            )}
                                        </Badge>
                                    )}
                                </div>
                            </div>
                            <CardDescription className="break-all">
                                ID pencatatan: {trial.id}
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
                                                'Mendapatkan akses privat',
                                                duration(
                                                    trial.authentication_ms,
                                                ),
                                            ],
                                            [
                                                'Menjangkau server SITA',
                                                duration(trial.reachability_ms),
                                            ],
                                            [
                                                'Masuk server dan cek Docker',
                                                duration(trial.ssh_ms),
                                            ],
                                            [
                                                'Total waktu pemeriksaan',
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
                                            Bukti identitas GitHub:{' '}
                                            <strong>
                                                {trial.metadata
                                                    .signature_verified_by_observatory
                                                    ? 'Terverifikasi'
                                                    : 'Belum diverifikasi'}
                                            </strong>
                                        </span>
                                        <span>
                                            Hasil yang diharapkan:{' '}
                                            <strong>
                                                {decisionLabel(
                                                    trial.expected_decision,
                                                )}
                                            </strong>
                                        </span>
                                        <span>
                                            Hasil akses:{' '}
                                            <strong>
                                                {decisionLabel(
                                                    trial.actual_decision,
                                                )}
                                            </strong>
                                        </span>
                                        <span>
                                            Jalur koneksi:{' '}
                                            <strong>
                                                {networkPathLabel(
                                                    trial.network_path,
                                                )}
                                            </strong>
                                        </span>
                                        <span>
                                            Data dapat digunakan:{' '}
                                            <strong>
                                                {trial.metadata
                                                    .measurement_valid
                                                    ? 'Ya'
                                                    : 'Tidak, pemeriksaan belum lengkap'}
                                            </strong>
                                        </span>
                                    </div>
                                    <div className="overflow-x-auto rounded-xl border">
                                        <table className="w-full text-left text-sm">
                                            <thead className="bg-muted/40">
                                                <tr>
                                                    <th className="p-3">
                                                        Tahap pemeriksaan
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
                                                                {stageStatusLabels[
                                                                    stage.status
                                                                ] ?? stage.status}
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
                                        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                            <h2 className="flex items-center gap-2 font-medium">
                                                <ShieldCheck className="size-4" />{' '}
                                                Data identitas dari GitHub
                                            </h2>
                                            <div
                                                className="bg-muted inline-flex w-fit rounded-lg border p-1"
                                                role="group"
                                                aria-label="Pilih cara membaca data identitas GitHub"
                                            >
                                                <button
                                                    type="button"
                                                    className={`rounded-md px-3 py-1.5 text-sm font-medium transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 ${
                                                        claimView ===
                                                        'technical'
                                                            ? 'bg-background text-foreground shadow-sm'
                                                            : 'text-muted-foreground hover:text-foreground'
                                                    }`}
                                                    aria-pressed={
                                                        claimView ===
                                                        'technical'
                                                    }
                                                    onClick={() =>
                                                        setClaimView(
                                                            'technical',
                                                        )
                                                    }
                                                >
                                                    Data asli
                                                </button>
                                                <button
                                                    type="button"
                                                    className={`rounded-md px-3 py-1.5 text-sm font-medium transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 ${
                                                        claimView === 'guided'
                                                            ? 'bg-background text-foreground shadow-sm'
                                                            : 'text-muted-foreground hover:text-foreground'
                                                    }`}
                                                    aria-pressed={
                                                        claimView === 'guided'
                                                    }
                                                    onClick={() =>
                                                        setClaimView('guided')
                                                    }
                                                >
                                                    Panduan baca
                                                </button>
                                            </div>
                                        </div>
                                        {claimView === 'technical' ? (
                                            <div className="space-y-4">
                                                {groupClaims(
                                                    trial.metadata.evidence
                                                        .oidc_claims ?? {},
                                                ).map((section) => (
                                                    <section
                                                        key={section.id}
                                                        className="overflow-hidden rounded-xl border"
                                                    >
                                                        <header className="bg-muted/40 border-b px-4 py-3">
                                                            <h3 className="text-sm font-medium">
                                                                {section.label}
                                                            </h3>
                                                            <p className="text-muted-foreground mt-1 text-xs leading-5">
                                                                {
                                                                    section.description
                                                                }
                                                            </p>
                                                        </header>
                                                        <dl className="divide-y">
                                                            {section.entries.map(
                                                                ([key, value]) => {
                                                                    const guide =
                                                                        claimGuide(
                                                                            key,
                                                                        );
                                                                    const readableTime =
                                                                        guide.kind ===
                                                                        'time'
                                                                            ? formatUnixTime(
                                                                                  value,
                                                                              )
                                                                            : null;
                                                                    const isExplained =
                                                                        explainedClaim ===
                                                                        key;
                                                                    const explanationId = `claim-explanation-${key}`;

                                                                    return (
                                                                        <div
                                                                            key={
                                                                                key
                                                                            }
                                                                            className="px-4 py-3"
                                                                        >
                                                                            <dt className="flex items-center justify-between gap-3">
                                                                                <div className="flex min-w-0 flex-wrap items-center gap-x-2 gap-y-1">
                                                                                    <span className="text-sm font-medium">
                                                                                        {
                                                                                            guide.label
                                                                                        }
                                                                                    </span>
                                                                                    <code
                                                                                        className="text-muted-foreground text-xs"
                                                                                        translate="no"
                                                                                    >
                                                                                        {
                                                                                            key
                                                                                        }
                                                                                    </code>
                                                                                </div>
                                                                                <button
                                                                                    type="button"
                                                                                    className="text-muted-foreground hover:bg-muted hover:text-foreground inline-flex size-7 shrink-0 items-center justify-center rounded-full transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600"
                                                                                    aria-expanded={
                                                                                        isExplained
                                                                                    }
                                                                                    aria-controls={
                                                                                        explanationId
                                                                                    }
                                                                                    aria-label={`Jelaskan data ${key}`}
                                                                                    onClick={() =>
                                                                                        setExplainedClaim(
                                                                                            isExplained
                                                                                                ? null
                                                                                                : key,
                                                                                        )
                                                                                    }
                                                                                >
                                                                                    <CircleHelp
                                                                                        className="size-4"
                                                                                        aria-hidden="true"
                                                                                    />
                                                                                </button>
                                                                            </dt>
                                                                            <dd className="mt-1 min-w-0">
                                                                                <code
                                                                                    className="text-muted-foreground block overflow-x-auto text-sm break-all whitespace-pre-wrap"
                                                                                    translate="no"
                                                                                >
                                                                                    {String(
                                                                                        value ??
                                                                                            'Tidak tersedia',
                                                                                    )}
                                                                                </code>
                                                                            </dd>
                                                                            {isExplained && (
                                                                                <dd
                                                                                    id={
                                                                                        explanationId
                                                                                    }
                                                                                    className="bg-muted/30 mt-3 space-y-2 rounded-lg border p-3 text-sm leading-6"
                                                                                >
                                                                                    <p>
                                                                                        {
                                                                                            guide.summary
                                                                                        }
                                                                                    </p>
                                                                                    {readableTime && (
                                                                                        <p>
                                                                                            <span className="text-muted-foreground">
                                                                                                Waktu
                                                                                                WITA:{' '}
                                                                                            </span>
                                                                                            <strong>
                                                                                                {
                                                                                                    readableTime
                                                                                                }
                                                                                            </strong>
                                                                                        </p>
                                                                                    )}
                                                                                    <p>
                                                                                        <span className="font-medium">
                                                                                            Cara
                                                                                            membaca:{' '}
                                                                                        </span>
                                                                                        {
                                                                                            guide.reading
                                                                                        }
                                                                                    </p>
                                                                                </dd>
                                                                            )}
                                                                        </div>
                                                                    );
                                                                },
                                                            )}
                                                        </dl>
                                                    </section>
                                                ))}
                                            </div>
                                        ) : (
                                            <div className="space-y-5">
                                                <p className="text-muted-foreground text-sm leading-6">
                                                    Buka salah satu baris untuk
                                                    melihat arti dan cara
                                                    membacanya. Nilai asli tetap
                                                    ditampilkan sebagai bukti
                                                    penelitian.
                                                </p>
                                                {groupClaims(
                                                    trial.metadata.evidence
                                                        .oidc_claims ?? {},
                                                ).map((section) => (
                                                    <section
                                                        key={section.id}
                                                        className="space-y-2"
                                                    >
                                                        <header>
                                                            <h3 className="text-sm font-medium">
                                                                {section.label}
                                                            </h3>
                                                            <p className="text-muted-foreground mt-1 text-xs leading-5">
                                                                {
                                                                    section.description
                                                                }
                                                            </p>
                                                        </header>
                                                        <div className="grid gap-3">
                                                            {section.entries.map(
                                                                ([
                                                                    key,
                                                                    value,
                                                                ]) => {
                                                                    const guide =
                                                                        claimGuide(
                                                                            key,
                                                                        );
                                                                    const readableTime =
                                                                        guide.kind ===
                                                                        'time'
                                                                            ? formatUnixTime(
                                                                                  value,
                                                                              )
                                                                            : null;

                                                                    return (
                                                                        <details
                                                                            key={
                                                                                key
                                                                            }
                                                                            className="group overflow-hidden rounded-xl border"
                                                                        >
                                                                            <summary className="hover:bg-muted/40 flex min-h-16 cursor-pointer list-none items-center gap-3 p-4 transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 [&::-webkit-details-marker]:hidden">
                                                                                <div className="min-w-0 flex-1">
                                                                                    <div className="flex items-center gap-2">
                                                                                        <code
                                                                                            className="text-sm font-semibold"
                                                                                            translate="no"
                                                                                        >
                                                                                            {
                                                                                                key
                                                                                            }
                                                                                        </code>
                                                                                        <span
                                                                                            className="inline-flex size-6 items-center justify-center rounded-full text-indigo-600"
                                                                                            title={`Jelaskan klaim ${key}`}
                                                                                            aria-label={`Jelaskan klaim ${key}`}
                                                                                        >
                                                                                            <CircleHelp
                                                                                                className="size-4"
                                                                                                aria-hidden="true"
                                                                                            />
                                                                                        </span>
                                                                                    </div>
                                                                                    <code
                                                                                        className="text-muted-foreground mt-1 block overflow-x-auto text-sm break-all whitespace-pre-wrap"
                                                                                        translate="no"
                                                                                    >
                                                                                        {String(
                                                                                            value ??
                                                                                                'Tidak tersedia',
                                                                                        )}
                                                                                    </code>
                                                                                </div>
                                                                                <ChevronDown
                                                                                    className="text-muted-foreground size-4 shrink-0 transition-transform group-open:rotate-180"
                                                                                    aria-hidden="true"
                                                                                />
                                                                            </summary>
                                                                            <div className="bg-muted/30 space-y-3 border-t px-4 py-4">
                                                                                <div>
                                                                                    <p className="font-medium">
                                                                                        {
                                                                                            guide.label
                                                                                        }
                                                                                    </p>
                                                                                    <p className="text-muted-foreground mt-1 text-sm leading-6">
                                                                                        {
                                                                                            guide.summary
                                                                                        }
                                                                                    </p>
                                                                                </div>
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
                                                                                <div className="bg-background rounded-lg border p-3 text-sm leading-6">
                                                                                    <span className="font-medium">
                                                                                        Cara
                                                                                        membaca:{' '}
                                                                                    </span>
                                                                                    {
                                                                                        guide.reading
                                                                                    }
                                                                                </div>
                                                                            </div>
                                                                        </details>
                                                                    );
                                                                },
                                                            )}
                                                        </div>
                                                    </section>
                                                ))}
                                            </div>
                                        )}
                                    </section>
                                    <div className="space-y-2 rounded-xl border p-4 text-xs">
                                        <p className="break-all">
                                            Versi kode SITA (SHA commit):{' '}
                                            <code>
                                                {
                                                    trial.metadata.evidence
                                                        .github.sha
                                                }
                                            </code>
                                        </p>
                                        <p className="break-all">
                                            Sidik jari bukti (SHA-256):{' '}
                                            <code>
                                                {trial.metadata.evidence_sha256}
                                            </code>
                                        </p>
                                        <p>
                                            Versi pencatat:{' '}
                                            {
                                                trial.metadata.evidence
                                                    .integrity.collector_version
                                            }{' '}
                                            · Aturan uji:{' '}
                                            {
                                                trial.metadata.evidence
                                                    .integrity.policy_version
                                            }
                                        </p>
                                        <p>
                                            · Dicatat:{' '}
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
                            <FileJson className="size-4" /> Unggah bukti dari GitHub
                        </CardTitle>
                        <CardDescription>
                            Unggah berkas <code>trial-evidence.json</code> dari
                            hasil GitHub Actions. Sistem memeriksa ID, branch,
                            versi kode, dan hasil uji sebelum menyimpan bukti.
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
