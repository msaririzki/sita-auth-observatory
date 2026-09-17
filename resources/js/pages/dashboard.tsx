import { Head, Link } from '@inertiajs/react';
import {
    Activity,
    ArrowRight,
    CheckCircle2,
    FlaskConical,
    KeyRound,
    Radio,
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
import { dashboard } from '@/routes';

type Summary = {
    experiments: number;
    active: number;
    completed_trials: number;
    decision_accuracy: number | null;
};

type RecentExperiment = {
    id: string;
    name: string;
    profile: string;
    profile_label: string;
    scenario_label: string;
    status: string;
    trials_count: number;
    completed_trials_count: number;
};

type DashboardProps = {
    summary: Summary;
    recentExperiments: RecentExperiment[];
    dispatchReady: boolean;
};

const profiles = [
    {
        name: 'OAuth statis',
        description:
            'Akses memakai identitas dan rahasia yang disimpan lebih lama.',
        icon: KeyRound,
        tone: 'bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300',
    },
    {
        name: 'WIF dasar',
        description:
            'GitHub meminta akses sementara berdasarkan identitas proses yang berjalan.',
        icon: Radio,
        tone: 'bg-blue-50 text-blue-700 dark:bg-blue-950/40 dark:text-blue-300',
    },
    {
        name: 'WIF multi-klaim',
        description:
            'Akses sementara hanya diberikan untuk repositori, branch, dan workflow yang disetujui.',
        icon: ShieldCheck,
        tone: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300',
    },
];

function statusLabel(status: string): string {
    const labels: Record<string, string> = {
        draft: 'Draft',
        queued: 'Antrean',
        running: 'Berjalan',
        completed: 'Selesai',
        failed: 'Gagal',
        cancelled: 'Dibatalkan',
    };

    return labels[status] ?? status;
}

export default function Dashboard({
    summary,
    recentExperiments,
    dispatchReady,
}: DashboardProps) {
    const stats = [
        {
            label: 'Total eksperimen',
            value: summary.experiments.toLocaleString('id-ID'),
            icon: FlaskConical,
        },
        {
            label: 'Sedang berjalan',
            value: summary.active.toLocaleString('id-ID'),
            icon: Activity,
        },
        {
            label: 'Percobaan selesai',
            value: summary.completed_trials.toLocaleString('id-ID'),
            icon: CheckCircle2,
        },
        {
            label: 'Hasil sesuai harapan',
            value:
                summary.decision_accuracy === null
                    ? 'Belum ada data'
                    : `${summary.decision_accuracy.toLocaleString('id-ID')}%`,
            icon: ShieldCheck,
        },
    ];

    return (
        <>
            <Head title="Overview" />
            <div className="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-6 p-4 md:p-6">
                <section className="flex flex-col justify-between gap-4 md:flex-row md:items-end">
                    <div className="space-y-2">
                        <div className="flex items-center gap-2 text-sm font-medium text-indigo-700 dark:text-indigo-300">
                            <span className="relative flex size-2">
                                <span className="absolute inline-flex size-full animate-ping rounded-full bg-indigo-400 opacity-50 motion-reduce:animate-none" />
                                <span className="relative inline-flex size-2 rounded-full bg-indigo-600" />
                            </span>
                            SITA Auth Observatory
                        </div>
                        <h1 className="text-2xl font-semibold tracking-tight text-balance md:text-3xl">
                            Pemantauan uji akses SITA
                        </h1>
                        <p className="text-muted-foreground max-w-2xl text-sm leading-6 md:text-base">
                            Bandingkan cara GitHub Actions mendapat akses aman
                            ke server SITA. Setiap uji menyimpan hasil dan
                            bukti teknisnya secara terpisah.
                        </p>
                    </div>
                    <Button asChild>
                        <Link href="/experiments">
                            Buat eksperimen
                            <ArrowRight aria-hidden="true" />
                        </Link>
                    </Button>
                </section>

                {!dispatchReady && (
                    <div
                        className="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-100"
                        role="status"
                    >
                        Pengiriman uji dari halaman ini belum dihubungkan ke
                        GitHub. Anda tetap dapat membuat rencana uji, lalu
                        menjalankannya dari GitHub Actions.
                    </div>
                )}

                <section
                    className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4"
                    aria-label="Ringkasan penelitian"
                >
                    {stats.map((stat) => (
                        <Card
                            key={stat.label}
                            className="gap-4 py-5 shadow-none"
                        >
                            <CardContent className="flex items-start justify-between px-5">
                                <div className="space-y-1">
                                    <p className="text-muted-foreground text-sm">
                                        {stat.label}
                                    </p>
                                    <p className="text-2xl font-semibold tabular-nums">
                                        {stat.value}
                                    </p>
                                </div>
                                <div className="rounded-lg bg-indigo-50 p-2 text-indigo-700 dark:bg-indigo-950/50 dark:text-indigo-300">
                                    <stat.icon
                                        className="size-5"
                                        aria-hidden="true"
                                    />
                                </div>
                            </CardContent>
                        </Card>
                    ))}
                </section>

                <section className="grid gap-6 xl:grid-cols-[1.1fr_0.9fr]">
                    <Card className="shadow-none">
                        <CardHeader>
                            <CardTitle>Cara akses yang dibandingkan</CardTitle>
                            <CardDescription>
                                Tiga cara GitHub mendapat akses ke server SITA.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-3">
                            {profiles.map((profile, index) => (
                                <div
                                    key={profile.name}
                                    className="flex gap-4 rounded-xl border p-4"
                                >
                                    <div
                                        className={`flex size-10 shrink-0 items-center justify-center rounded-lg ${profile.tone}`}
                                    >
                                        <profile.icon
                                            className="size-5"
                                            aria-hidden="true"
                                        />
                                    </div>
                                    <div className="min-w-0 space-y-1">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <p className="font-medium">
                                                {profile.name}
                                            </p>
                                            <Badge variant="outline">
                                                Profil {index + 1}
                                            </Badge>
                                        </div>
                                        <p className="text-muted-foreground text-sm leading-5">
                                            {profile.description}
                                        </p>
                                    </div>
                                </div>
                            ))}
                        </CardContent>
                    </Card>

                    <Card className="shadow-none">
                        <CardHeader className="flex-row items-start justify-between gap-4">
                            <div className="space-y-1.5">
                                <CardTitle>Eksperimen terbaru</CardTitle>
                                <CardDescription>
                                    Progres pengulangan yang tersimpan.
                                </CardDescription>
                            </div>
                            <Button variant="ghost" size="sm" asChild>
                                <Link href="/experiments">Lihat semua</Link>
                            </Button>
                        </CardHeader>
                        <CardContent>
                            {recentExperiments.length === 0 ? (
                                <div className="flex min-h-56 flex-col items-center justify-center rounded-xl border border-dashed px-6 text-center">
                                    <FlaskConical
                                        className="text-muted-foreground mb-3 size-8"
                                        aria-hidden="true"
                                    />
                                    <p className="font-medium">
                                        Belum ada eksperimen
                                    </p>
                                    <p className="text-muted-foreground mt-1 max-w-xs text-sm">
                                        Buat rancangan pertama untuk memulai
                                        pencatatan data penelitian.
                                    </p>
                                </div>
                            ) : (
                                <div className="divide-y">
                                    {recentExperiments.map((experiment) => (
                                        <div
                                            key={experiment.id}
                                            className="flex items-center justify-between gap-4 py-3 first:pt-0 last:pb-0"
                                        >
                                            <div className="min-w-0">
                                                <p className="truncate text-sm font-medium">
                                                    {experiment.name}
                                                </p>
                                                <p className="text-muted-foreground truncate text-xs">
                                                    {experiment.profile_label} ·{' '}
                                                    {experiment.scenario_label}
                                                </p>
                                            </div>
                                            <div className="shrink-0 text-right">
                                                <Badge variant="outline">
                                                    {statusLabel(
                                                        experiment.status,
                                                    )}
                                                </Badge>
                                                <p className="text-muted-foreground mt-1 text-xs tabular-nums">
                                                    {
                                                        experiment.completed_trials_count
                                                    }
                                                    /{experiment.trials_count}
                                                </p>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </section>
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Overview',
            href: dashboard(),
        },
    ],
};
