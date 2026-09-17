import { type FormEvent } from 'react';
import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    Clock3,
    FlaskConical,
    GitBranch,
    Play,
    Server,
    ShieldAlert,
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

type Option = {
    value: string;
    label: string;
    expected_decision?: string;
};

type Experiment = {
    id: string;
    name: string;
    profile_label: string;
    scenario_label: string;
    target: string;
    git_ref: string;
    status: string;
    trials_count: number;
    completed_trials_count: number;
};

type PaginatedExperiments = {
    data: Experiment[];
    total: number;
};

type ExperimentsProps = {
    experiments: PaginatedExperiments;
    options: {
        profiles: Option[];
        scenarios: Option[];
        targets: string[];
    };
    dispatchReady: boolean;
};

const statusLabels: Record<string, string> = {
    draft: 'Draft',
    queued: 'Antrean',
    running: 'Berjalan',
    completed: 'Selesai',
    failed: 'Gagal',
    cancelled: 'Dibatalkan',
};

export default function ExperimentsIndex({
    experiments,
    options,
    dispatchReady,
}: ExperimentsProps) {
    const form = useForm({
        name: '',
        profile: options.profiles[0]?.value ?? 'oauth_static',
        scenario: options.scenarios[0]?.value ?? 'valid',
        target: options.targets[0] ?? 'sita-docker',
        git_ref: 'main',
        commit_sha: '',
        repetitions: 10,
        cooldown_seconds: 45,
    });

    const selectedScenario = options.scenarios.find(
        (scenario) => scenario.value === form.data.scenario,
    );

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.post('/experiments', {
            preserveScroll: true,
            onSuccess: () => form.reset('name', 'commit_sha'),
        });
    }

    return (
        <>
            <Head title="Experiments" />
            <div className="mx-auto grid w-full max-w-7xl flex-1 gap-6 p-4 lg:grid-cols-[minmax(0,1fr)_24rem] lg:p-6">
                <section className="min-w-0 space-y-5">
                    <div className="space-y-2">
                        <p className="text-sm font-medium text-indigo-700 dark:text-indigo-300">
                            Experiment workspace
                        </p>
                        <h1 className="text-2xl font-semibold tracking-tight text-balance">
                            Eksperimen autentikasi
                        </h1>
                        <p className="text-muted-foreground max-w-2xl text-sm leading-6">
                            Setiap eksperimen menguji satu profil secara
                            berurutan agar waktu dan keputusan autentikasi dapat
                            dibandingkan secara adil.
                        </p>
                    </div>

                    <Card className="shadow-none">
                        <CardHeader className="flex-row items-start justify-between gap-4">
                            <div className="space-y-1.5">
                                <CardTitle>Riwayat eksperimen</CardTitle>
                                <CardDescription>
                                    {experiments.total.toLocaleString('id-ID')}{' '}
                                    rancangan tersimpan
                                </CardDescription>
                            </div>
                            <Badge
                                variant={dispatchReady ? 'default' : 'outline'}
                            >
                                {dispatchReady
                                    ? 'Kontrol GitHub siap'
                                    : 'Mode rancangan'}
                            </Badge>
                        </CardHeader>
                        <CardContent>
                            {experiments.data.length === 0 ? (
                                <div className="flex min-h-64 flex-col items-center justify-center rounded-xl border border-dashed px-6 text-center">
                                    <FlaskConical
                                        className="text-muted-foreground mb-3 size-9"
                                        aria-hidden="true"
                                    />
                                    <p className="font-medium">
                                        Belum ada eksperimen
                                    </p>
                                    <p className="text-muted-foreground mt-1 max-w-sm text-sm">
                                        Isi form di samping untuk membuat batch
                                        percobaan pertama.
                                    </p>
                                </div>
                            ) : (
                                <div className="space-y-3">
                                    {experiments.data.map((experiment) => {
                                        const progress =
                                            experiment.trials_count > 0
                                                ? (experiment.completed_trials_count /
                                                      experiment.trials_count) *
                                                  100
                                                : 0;

                                        return (
                                            <article
                                                key={experiment.id}
                                                className="rounded-xl border p-4"
                                            >
                                                <div className="flex flex-col justify-between gap-3 sm:flex-row sm:items-start">
                                                    <div className="min-w-0 space-y-2">
                                                        <div className="flex flex-wrap items-center gap-2">
                                                            <h2 className="font-medium">
                                                                {
                                                                    experiment.name
                                                                }
                                                            </h2>
                                                            <Badge variant="outline">
                                                                {statusLabels[
                                                                    experiment
                                                                        .status
                                                                ] ??
                                                                    experiment.status}
                                                            </Badge>
                                                        </div>
                                                        <div className="text-muted-foreground flex flex-wrap gap-x-4 gap-y-1 text-xs">
                                                            <span>
                                                                {
                                                                    experiment.profile_label
                                                                }
                                                            </span>
                                                            <span>
                                                                {
                                                                    experiment.scenario_label
                                                                }
                                                            </span>
                                                            <span>
                                                                {
                                                                    experiment.target
                                                                }
                                                            </span>
                                                            <span>
                                                                {
                                                                    experiment.git_ref
                                                                }
                                                            </span>
                                                        </div>
                                                    </div>
                                                    <div className="flex shrink-0 items-center gap-3">
                                                        <Button
                                                            variant="outline"
                                                            size="sm"
                                                            asChild
                                                        >
                                                            <Link
                                                                href={`/experiments/${experiment.id}`}
                                                            >
                                                                Lihat bukti
                                                            </Link>
                                                        </Button>
                                                        <span className="text-sm tabular-nums">
                                                            {
                                                                experiment.completed_trials_count
                                                            }
                                                            /
                                                            {
                                                                experiment.trials_count
                                                            }{' '}
                                                            selesai
                                                        </span>
                                                        {dispatchReady &&
                                                            experiment.status ===
                                                                'draft' && (
                                                                <Button
                                                                    type="button"
                                                                    size="sm"
                                                                    onClick={() =>
                                                                        router.post(
                                                                            `/experiments/${experiment.id}/dispatch`,
                                                                            {},
                                                                            {
                                                                                preserveScroll: true,
                                                                            },
                                                                        )
                                                                    }
                                                                >
                                                                    <Play aria-hidden="true" />
                                                                    Jalankan
                                                                </Button>
                                                            )}
                                                    </div>
                                                </div>
                                                <div
                                                    className="bg-muted mt-4 h-1.5 overflow-hidden rounded-full"
                                                    aria-label={`Progres ${Math.round(progress)} persen`}
                                                    aria-valuemax={100}
                                                    aria-valuemin={0}
                                                    aria-valuenow={Math.round(
                                                        progress,
                                                    )}
                                                    role="progressbar"
                                                >
                                                    <div
                                                        className="h-full origin-left rounded-full bg-indigo-600 transition-transform motion-reduce:transition-none"
                                                        style={{
                                                            transform: `scaleX(${progress / 100})`,
                                                        }}
                                                    />
                                                </div>
                                            </article>
                                        );
                                    })}
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </section>

                <aside>
                    <Card className="sticky top-6 shadow-none">
                        <CardHeader>
                            <CardTitle>Rancang eksperimen</CardTitle>
                            <CardDescription>
                                Percobaan akan dibuat berurutan, bukan paralel.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <form className="space-y-5" onSubmit={submit}>
                                <div className="space-y-2">
                                    <Label htmlFor="name">
                                        Nama eksperimen
                                    </Label>
                                    <Input
                                        id="name"
                                        name="name"
                                        autoComplete="off"
                                        value={form.data.name}
                                        onChange={(event) =>
                                            form.setData(
                                                'name',
                                                event.target.value,
                                            )
                                        }
                                        placeholder="Contoh: Pilot WIF dasar…"
                                        aria-invalid={Boolean(form.errors.name)}
                                    />
                                    {form.errors.name && (
                                        <p className="text-destructive text-xs">
                                            {form.errors.name}
                                        </p>
                                    )}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="profile">
                                        Profil autentikasi
                                    </Label>
                                    <Select
                                        name="profile"
                                        value={form.data.profile}
                                        onValueChange={(value) =>
                                            form.setData('profile', value)
                                        }
                                    >
                                        <SelectTrigger
                                            id="profile"
                                            className="w-full"
                                        >
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {options.profiles.map((profile) => (
                                                <SelectItem
                                                    key={profile.value}
                                                    value={profile.value}
                                                >
                                                    {profile.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="scenario">Skenario</Label>
                                    <Select
                                        name="scenario"
                                        value={form.data.scenario}
                                        onValueChange={(value) =>
                                            form.setData('scenario', value)
                                        }
                                    >
                                        <SelectTrigger
                                            id="scenario"
                                            className="w-full"
                                        >
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {options.scenarios.map(
                                                (scenario) => (
                                                    <SelectItem
                                                        key={scenario.value}
                                                        value={scenario.value}
                                                    >
                                                        {scenario.label}
                                                    </SelectItem>
                                                ),
                                            )}
                                        </SelectContent>
                                    </Select>
                                    <p className="text-muted-foreground flex items-center gap-1.5 text-xs">
                                        <ShieldAlert
                                            className="size-3.5"
                                            aria-hidden="true"
                                        />
                                        Keputusan yang diharapkan:{' '}
                                        <strong className="uppercase">
                                            {selectedScenario?.expected_decision ??
                                                '—'}
                                        </strong>
                                    </p>
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="target">
                                        Target privat
                                    </Label>
                                    <Select
                                        name="target"
                                        value={form.data.target}
                                        onValueChange={(value) =>
                                            form.setData('target', value)
                                        }
                                    >
                                        <SelectTrigger
                                            id="target"
                                            className="w-full"
                                        >
                                            <Server
                                                className="size-4"
                                                aria-hidden="true"
                                            />
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {options.targets.map((target) => (
                                                <SelectItem
                                                    key={target}
                                                    value={target}
                                                >
                                                    {target}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>

                                <div className="grid grid-cols-2 gap-3">
                                    <div className="space-y-2">
                                        <Label htmlFor="git_ref">
                                            Git reference
                                        </Label>
                                        <div className="relative">
                                            <GitBranch
                                                className="text-muted-foreground absolute top-2.5 left-3 size-4"
                                                aria-hidden="true"
                                            />
                                            <Input
                                                id="git_ref"
                                                name="git_ref"
                                                autoComplete="off"
                                                spellCheck={false}
                                                className="pl-9"
                                                value={form.data.git_ref}
                                                onChange={(event) =>
                                                    form.setData(
                                                        'git_ref',
                                                        event.target.value,
                                                    )
                                                }
                                                aria-invalid={Boolean(
                                                    form.errors.git_ref,
                                                )}
                                            />
                                        </div>
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="repetitions">
                                            Pengulangan
                                        </Label>
                                        <Input
                                            id="repetitions"
                                            name="repetitions"
                                            autoComplete="off"
                                            type="number"
                                            min={1}
                                            max={30}
                                            value={form.data.repetitions}
                                            onChange={(event) =>
                                                form.setData(
                                                    'repetitions',
                                                    Number(event.target.value),
                                                )
                                            }
                                        />
                                    </div>
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="cooldown_seconds">
                                        Jeda antarpercobaan
                                    </Label>
                                    <div className="relative">
                                        <Clock3
                                            className="text-muted-foreground absolute top-2.5 left-3 size-4"
                                            aria-hidden="true"
                                        />
                                        <Input
                                            id="cooldown_seconds"
                                            name="cooldown_seconds"
                                            autoComplete="off"
                                            className="pl-9"
                                            type="number"
                                            min={0}
                                            max={300}
                                            value={form.data.cooldown_seconds}
                                            onChange={(event) =>
                                                form.setData(
                                                    'cooldown_seconds',
                                                    Number(event.target.value),
                                                )
                                            }
                                        />
                                    </div>
                                    <p className="text-muted-foreground text-xs">
                                        Detik. Nilai awal 45 untuk mengurangi
                                        pengaruh sesi sebelumnya.
                                    </p>
                                </div>

                                <Button
                                    type="submit"
                                    className="w-full"
                                    disabled={form.processing}
                                >
                                    <Play aria-hidden="true" />
                                    {form.processing
                                        ? 'Menyimpan…'
                                        : 'Buat rancangan eksperimen'}
                                </Button>

                                {!dispatchReady && (
                                    <p className="text-muted-foreground text-center text-xs leading-5">
                                        Rancangan disimpan sebagai draft sampai
                                        GitHub App dihubungkan.
                                    </p>
                                )}
                            </form>
                        </CardContent>
                    </Card>
                </aside>
            </div>
        </>
    );
}

ExperimentsIndex.layout = {
    breadcrumbs: [
        {
            title: 'Experiments',
            href: '/experiments',
        },
    ],
};
