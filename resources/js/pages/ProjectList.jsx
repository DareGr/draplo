import { useState, useEffect } from 'react';
import { useNavigate, Link } from 'react-router-dom';
import api from '../api';
import AppLayout from '../components/AppLayout';
import Button from '../components/Button';
import Toast from '../components/Toast';

const STATUS_STYLES = {
    draft: { dot: 'bg-amber-500', label: 'Draft' },
    wizard_done: { dot: 'bg-primary', label: 'Wizard Done' },
    generating: { dot: 'bg-secondary animate-pulse', label: 'Generating' },
    generated: { dot: 'bg-secondary', label: 'Generated' },
    exported: { dot: 'bg-green-500', label: 'Exported' },
    deploying: { dot: 'bg-secondary animate-pulse', label: 'Deploying' },
    deployed: { dot: 'bg-green-500', label: 'Deployed' },
    failed: { dot: 'bg-error', label: 'Failed' },
};

export default function ProjectList() {
    const navigate = useNavigate();
    const [projects, setProjects] = useState([]);
    const [loading, setLoading] = useState(true);
    const [toast, setToast] = useState(null);

    useEffect(() => {
        api.get('/projects')
            .then(({ data }) => setProjects(data))
            .catch(() => setToast({ message: 'Failed to load projects', type: 'error' }))
            .finally(() => setLoading(false));
    }, []);

    const handleDelete = async (project) => {
        if (!window.confirm(`Delete "${project.name}"? This cannot be undone.`)) return;
        try {
            await api.delete(`/projects/${project.id}`);
            setProjects(prev => prev.filter(p => p.id !== project.id));
            setToast({ message: `"${project.name}" deleted`, type: 'success' });
        } catch {
            setToast({ message: 'Failed to delete project', type: 'error' });
        }
    };

    const status = (s) => STATUS_STYLES[s] || STATUS_STYLES.draft;

    return (
        <AppLayout activePage="dashboard">
            <div className="p-12 pb-20">
                <section className="max-w-4xl mx-auto">
                    <div className="flex items-center justify-between mb-10">
                        <div>
                            <span className="text-primary font-mono text-xs tracking-widest uppercase">Dashboard</span>
                            <h1 className="text-4xl font-extrabold tracking-tight text-white font-headline mt-2">Your Projects</h1>
                        </div>
                        <Button variant="primary" onClick={() => navigate('/templates')}>
                            <span className="material-symbols-outlined mr-1 text-sm">add</span>
                            New Project
                        </Button>
                    </div>

                    {loading ? (
                        <div className="space-y-4">
                            {[...Array(3)].map((_, i) => (
                                <div key={i} className="bg-surface-container rounded-xl p-6 h-20 animate-pulse" />
                            ))}
                        </div>
                    ) : projects.length === 0 ? (
                        <div className="text-center py-20">
                            <span className="material-symbols-outlined text-5xl text-outline mb-4">folder_open</span>
                            <h2 className="text-xl font-bold text-white mb-2">No projects yet</h2>
                            <p className="text-on-surface-variant mb-6">Start by picking a template from the library.</p>
                            <Button variant="primary" onClick={() => navigate('/templates')}>Browse Templates</Button>
                        </div>
                    ) : (
                        <div className="space-y-3">
                            {projects.map((project) => {
                                const st = status(project.status);
                                const isDraft = project.status === 'draft';
                                return (
                                    <div
                                        key={project.id}
                                        className="bg-surface-container rounded-xl p-5 border border-outline-variant/5 flex items-center gap-4 hover:bg-surface-container-high transition-all"
                                    >
                                        {/* Name + template */}
                                        <div className="flex-1 min-w-0">
                                            <h3 className="text-on-surface font-bold truncate">{project.name}</h3>
                                            <p className="text-on-surface-variant text-xs font-mono mt-0.5">
                                                {project.template_slug
                                                    ? project.template_slug.replace(/-/g, ' ')
                                                    : 'Custom'}
                                            </p>
                                        </div>

                                        {/* Status */}
                                        <div className="flex items-center gap-1.5">
                                            <div className={`w-2 h-2 rounded-full ${st.dot}`}></div>
                                            <span className="font-mono text-[11px] text-on-surface-variant">{st.label}</span>
                                        </div>

                                        {/* Updated */}
                                        <span className="font-mono text-[11px] text-outline hidden sm:block">
                                            {new Date(project.updated_at).toLocaleDateString()}
                                        </span>

                                        {/* Actions */}
                                        <div className="flex items-center gap-2">
                                            {isDraft && (
                                                <Link
                                                    to={`/wizard/${project.id}`}
                                                    className="text-primary text-sm font-medium hover:text-primary-container transition-colors"
                                                >
                                                    Resume
                                                </Link>
                                            )}
                                            {project.status === 'generated' && (
                                                <Link
                                                    to={`/projects/${project.id}/preview`}
                                                    className="text-secondary text-sm font-medium hover:text-secondary-container transition-colors"
                                                >
                                                    Preview
                                                </Link>
                                            )}
                                            <button
                                                onClick={() => handleDelete(project)}
                                                className="text-outline hover:text-error transition-colors"
                                            >
                                                <span className="material-symbols-outlined text-xl">delete</span>
                                            </button>
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    )}
                </section>
            </div>
            {toast && <Toast message={toast.message} type={toast.type} onDismiss={() => setToast(null)} />}
        </AppLayout>
    );
}
