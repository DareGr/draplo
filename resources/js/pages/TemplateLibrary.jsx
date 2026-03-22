import { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import api from '../api';
import AppLayout from '../components/AppLayout';
import CategoryFilter from '../components/CategoryFilter';
import Toast from '../components/Toast';

const CATEGORIES = ['All', 'Operations', 'Sales', 'Content', 'Marketplace', 'Education', 'Health', 'Hospitality', 'Analytics', 'Specialized'];

const COMPLEXITY_STYLES = {
    'medium': { dot: 'bg-amber-500', text: 'text-amber-500' },
    'medium-high': { dot: 'bg-tertiary-container', text: 'text-tertiary-container' },
    'high': { dot: 'bg-error', text: 'text-error' },
};

export default function TemplateLibrary() {
    const navigate = useNavigate();
    const [templates, setTemplates] = useState([]);
    const [loading, setLoading] = useState(true);
    const [activeCategory, setActiveCategory] = useState('All');
    const [creating, setCreating] = useState(false);
    const [toast, setToast] = useState(null);

    useEffect(() => {
        api.get('/templates')
            .then(({ data }) => setTemplates(data))
            .catch(() => setToast({ message: 'Failed to load templates', type: 'error' }))
            .finally(() => setLoading(false));
    }, []);

    const filtered = activeCategory === 'All'
        ? templates
        : templates.filter(t => t.category?.toLowerCase() === activeCategory.toLowerCase());

    const handleTemplateClick = async (template) => {
        if (!template.available) {
            setToast({ message: `${template.name} — coming soon`, type: 'error' });
            return;
        }
        setCreating(true);
        try {
            const { data } = await api.post('/wizard/projects', { template_slug: template.slug });
            navigate(`/wizard/${data.id}`);
        } catch {
            setToast({ message: 'Failed to create project', type: 'error' });
            setCreating(false);
        }
    };

    const handleStartFromScratch = async () => {
        setCreating(true);
        try {
            const { data } = await api.post('/wizard/projects', {});
            navigate(`/wizard/${data.id}`);
        } catch {
            setToast({ message: 'Failed to create project', type: 'error' });
            setCreating(false);
        }
    };

    const complexity = (level) => COMPLEXITY_STYLES[level] || COMPLEXITY_STYLES['medium'];

    return (
        <AppLayout activePage="library">
            <div className="p-12 pb-20">
                {/* Header */}
                <section className="max-w-6xl mx-auto mb-12">
                    <span className="text-primary font-mono text-xs tracking-widest uppercase">System / Library / v1.0</span>
                    <h1 className="text-5xl font-extrabold tracking-tight text-white font-headline mt-2">Template Library</h1>
                    <p className="text-on-surface-variant text-lg max-w-2xl mt-2">25 industry-specific scaffolds. Pick one, customize it, ship it.</p>
                </section>

                {/* Filter */}
                <section className="max-w-6xl mx-auto mb-16">
                    <CategoryFilter categories={CATEGORIES} active={activeCategory} onChange={setActiveCategory} />
                </section>

                {/* Grid */}
                <section className="max-w-6xl mx-auto">
                    {loading ? (
                        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            {[...Array(6)].map((_, i) => (
                                <div key={i} className="bg-surface-container rounded-xl p-6 h-64 animate-pulse" />
                            ))}
                        </div>
                    ) : (
                        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            {/* Start from Scratch */}
                            <div
                                onClick={!creating ? handleStartFromScratch : undefined}
                                className="group relative bg-surface-container-lowest border-2 border-dashed border-outline-variant/30 rounded-xl p-8 flex flex-col items-center justify-center text-center hover:border-primary/50 transition-all cursor-pointer"
                            >
                                <div className="w-14 h-14 bg-primary/10 rounded-full flex items-center justify-center mb-6 group-hover:scale-110 transition-transform">
                                    <span className="material-symbols-outlined text-primary text-3xl" style={{ fontVariationSettings: "'FILL' 1" }}>auto_awesome</span>
                                </div>
                                <h3 className="text-xl font-bold text-white mb-2">Start from Scratch</h3>
                                <p className="text-on-surface-variant text-sm">Clean slate for custom architectural designs.</p>
                            </div>

                            {/* Template Cards */}
                            {filtered.map((template) => {
                                const cx = complexity(template.complexity);
                                return (
                                    <div
                                        key={template.slug}
                                        onClick={!creating ? () => handleTemplateClick(template) : undefined}
                                        className={`bg-surface-container hover:bg-surface-container-high transition-all duration-300 rounded-xl p-6 flex flex-col border border-outline-variant/5 group cursor-pointer ${!template.available ? 'opacity-60' : ''}`}
                                    >
                                        {/* Top */}
                                        <div className="flex justify-between items-start mb-6">
                                            <div className="w-10 h-10 bg-primary/10 rounded-lg flex items-center justify-center">
                                                <span className="material-symbols-outlined text-primary">{template.icon}</span>
                                            </div>
                                            <span className="px-3 py-1 bg-surface-container-highest text-primary-fixed-dim text-[10px] font-bold uppercase tracking-widest rounded border border-outline-variant/20">
                                                {template.category}
                                            </span>
                                        </div>

                                        {/* Name */}
                                        <h3 className="text-xl font-extrabold text-white mb-2 group-hover:text-primary transition-colors">
                                            {template.name}
                                        </h3>

                                        {/* Description */}
                                        <p className="text-on-surface-variant text-sm mb-6 leading-relaxed line-clamp-2">{template.description}</p>

                                        {/* Tags */}
                                        <div className="flex flex-wrap gap-2 mb-6">
                                            {(template.tags || []).map(tag => (
                                                <span key={tag} className="px-2 py-0.5 bg-background text-on-surface-variant text-[10px] font-mono rounded">{tag}</span>
                                            ))}
                                        </div>

                                        {/* Footer */}
                                        <div className="mt-auto pt-6 border-t border-outline-variant/10 flex items-center justify-between">
                                            <div className="flex gap-4">
                                                <div className="flex items-center gap-1.5">
                                                    <div className="w-2 h-2 rounded-full bg-secondary"></div>
                                                    <span className="font-mono text-[11px] text-secondary">{template.models_count} Models</span>
                                                </div>
                                                <div className="flex items-center gap-1.5">
                                                    <div className={`w-2 h-2 rounded-full ${cx.dot}`}></div>
                                                    <span className={`font-mono text-[11px] ${cx.text} capitalize`}>{template.complexity}</span>
                                                </div>
                                            </div>
                                            <span className="material-symbols-outlined text-outline group-hover:text-white transition-colors">arrow_forward</span>
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
