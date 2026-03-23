import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import api from '../api';
import AppLayout from '../components/AppLayout';
import Chip from '../components/Chip';

const statusDotColor = {
    draft: 'bg-outline',
    generating: 'bg-amber-400',
    generated: 'bg-primary',
    exported: 'bg-secondary',
    deployed: 'bg-green-400',
    failed: 'bg-tertiary-container',
};

const statusLabel = {
    draft: 'Draft',
    generating: 'Generating',
    generated: 'Generated',
    exported: 'Exported',
    deployed: 'Deployed',
    failed: 'Failed',
};

function StatCard({ icon, value, label }) {
    return (
        <div className="bg-surface-container rounded-xl p-5 border border-outline-variant/5">
            <div className="w-10 h-10 bg-primary/10 rounded-lg flex items-center justify-center mb-3">
                <span className="material-symbols-outlined text-primary text-xl">{icon}</span>
            </div>
            <div className="text-3xl font-extrabold font-mono text-white">{value}</div>
            <div className="font-label text-[11px] uppercase tracking-widest text-outline mt-1">{label}</div>
        </div>
    );
}

function formatDate(dateStr) {
    if (!dateStr) return '';
    const d = new Date(dateStr);
    return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

function formatLogDate(dateStr) {
    if (!dateStr) return '';
    const d = new Date(dateStr);
    return d.toISOString().slice(0, 10);
}

export default function Dashboard() {
    const [user, setUser] = useState(null);
    const [projects, setProjects] = useState([]);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        Promise.all([
            api.get('/auth/me'),
            api.get('/projects'),
        ])
            .then(([userRes, projectsRes]) => {
                setUser(userRes.data.user || userRes.data);
                setProjects(projectsRes.data.data || projectsRes.data || []);
            })
            .catch(() => {})
            .finally(() => setLoading(false));
    }, []);

    if (loading) {
        return (
            <AppLayout activePage="dashboard">
                <div className="p-8 flex items-center justify-center min-h-[60vh]">
                    <span className="text-primary font-mono text-sm">Loading dashboard...</span>
                </div>
            </AppLayout>
        );
    }

    const totalProjects = projects.length;
    const deployedCount = projects.filter((p) => p.status === 'deployed').length;
    const generatedCount = projects.filter((p) =>
        ['generated', 'exported', 'deployed'].includes(p.status)
    ).length;
    const recentProjects = [...projects]
        .sort((a, b) => new Date(b.updated_at) - new Date(a.updated_at))
        .slice(0, 5);

    const logEntries = projects
        .filter((p) => p.status && p.status !== 'draft')
        .sort((a, b) => new Date(b.updated_at) - new Date(a.updated_at));

    return (
        <AppLayout activePage="dashboard">
            <div className="p-8 max-w-6xl">
                {/* Stat Cards */}
                <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <StatCard icon="folder" value={totalProjects} label="Total Projects" />
                    <StatCard icon="rocket_launch" value={deployedCount} label="Deployed" />
                    <StatCard icon="smart_toy" value={generatedCount} label="Generated" />
                    <StatCard icon="favorite" value={
                        <span className="inline-flex items-center px-2.5 py-0.5 bg-primary/15 text-primary rounded text-lg font-bold font-mono">
                            Open Source
                        </span>
                    } label="Community" />
                </div>

                {/* Recent Projects */}
                <div className="mt-8">
                    <div className="flex items-center gap-2 mb-4">
                        <span className="material-symbols-outlined text-primary text-xl">folder_open</span>
                        <h2 className="text-on-surface text-lg font-bold">Recent Projects</h2>
                    </div>

                    {recentProjects.length === 0 ? (
                        <div className="bg-surface-container rounded-xl p-8 border border-outline-variant/5 text-center">
                            <span className="material-symbols-outlined text-4xl text-outline mb-3 block">folder_off</span>
                            <p className="text-on-surface-variant text-sm">No projects yet. Create your first project to get started.</p>
                            <Link
                                to="/templates"
                                className="inline-flex items-center gap-1.5 text-primary text-sm font-medium mt-4 hover:underline"
                            >
                                Browse Templates
                                <span className="material-symbols-outlined text-sm">arrow_forward</span>
                            </Link>
                        </div>
                    ) : (
                        <div className="space-y-2">
                            {recentProjects.map((project) => (
                                <div
                                    key={project.id}
                                    className="bg-surface-container rounded-xl px-5 py-4 border border-outline-variant/5 flex items-center justify-between gap-4"
                                >
                                    <div className="flex items-center gap-4 min-w-0 flex-1">
                                        <div className="min-w-0">
                                            <p className="text-on-surface font-bold text-sm truncate">{project.name}</p>
                                            <p className="font-mono text-[11px] text-on-surface-variant truncate mt-0.5">
                                                {project.template || 'Custom'}
                                            </p>
                                        </div>
                                    </div>

                                    <div className="flex items-center gap-4 shrink-0">
                                        <Chip
                                            label={statusLabel[project.status] || project.status}
                                            dotColor={statusDotColor[project.status] || 'bg-outline'}
                                        />
                                        <span className="font-mono text-[11px] text-on-surface-variant">
                                            {formatDate(project.updated_at)}
                                        </span>
                                        <div className="flex items-center gap-2">
                                            {['generated', 'exported', 'deployed'].includes(project.status) && (
                                                <Link
                                                    to={`/projects/${project.id}/preview`}
                                                    className="text-primary text-xs font-medium hover:underline"
                                                >
                                                    Preview
                                                </Link>
                                            )}
                                            {['generated', 'exported'].includes(project.status) && (
                                                <Link
                                                    to={`/projects/${project.id}/deploy`}
                                                    className="text-secondary text-xs font-medium hover:underline"
                                                >
                                                    Deploy
                                                </Link>
                                            )}
                                        </div>
                                    </div>
                                </div>
                            ))}

                            <Link
                                to="/projects"
                                className="inline-flex items-center gap-1 text-primary text-sm font-medium mt-3 hover:underline"
                            >
                                View All Projects
                                <span className="material-symbols-outlined text-sm">arrow_forward</span>
                            </Link>
                        </div>
                    )}
                </div>

                {/* AI Architect Terminal */}
                <div className="mt-8">
                    <div className="bg-surface-container-lowest rounded-xl p-5 font-mono text-xs border border-outline-variant/5">
                        <div className="flex items-center gap-2 mb-4">
                            <span className="material-symbols-outlined text-primary text-lg">terminal</span>
                            <span className="font-label text-[11px] uppercase tracking-widest text-on-surface-variant">
                                Architect Log
                            </span>
                        </div>

                        {logEntries.length === 0 ? (
                            <p className="text-on-surface-variant">
                                No generation events yet. Create a project to get started.
                            </p>
                        ) : (
                            <div className="space-y-1.5">
                                {logEntries.map((entry) => {
                                    let textColor = 'text-on-surface-variant';
                                    if (entry.status === 'deployed') textColor = 'text-green-400';
                                    else if (['generated', 'exported'].includes(entry.status)) textColor = 'text-primary';
                                    else if (entry.status === 'generating') textColor = 'text-amber-400';

                                    return (
                                        <div key={entry.id} className={textColor}>
                                            [{formatLogDate(entry.updated_at)}] {entry.name} via{' '}
                                            {entry.template || 'custom'} — {statusLabel[entry.status] || entry.status}
                                        </div>
                                    );
                                })}
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
