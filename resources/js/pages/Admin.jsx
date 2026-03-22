import { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import api from '../api';
import AppLayout from '../components/AppLayout';
import Input from '../components/Input';
import Button from '../components/Button';
import Toast from '../components/Toast';

function StatCard({ icon, value, label }) {
    return (
        <div className="bg-surface-container rounded-xl p-5 border border-outline-variant/5">
            <div className="w-10 h-10 bg-primary/10 rounded-lg flex items-center justify-center mb-3">
                <span className="material-symbols-outlined text-primary text-xl">{icon}</span>
            </div>
            <div className="text-2xl font-extrabold font-mono text-white">{value}</div>
            <div className="font-label text-[11px] uppercase tracking-widest text-outline mt-1">{label}</div>
        </div>
    );
}

export default function Admin() {
    const navigate = useNavigate();
    const [loading, setLoading] = useState(true);
    const [stats, setStats] = useState(null);
    const [settings, setSettings] = useState({
        provider: 'anthropic',
        model: '',
        max_tokens: '',
        rate_limit: '',
    });
    const [saving, setSaving] = useState(false);
    const [toast, setToast] = useState(null);

    useEffect(() => {
        api.get('/auth/me')
            .then((res) => {
                const user = res.data.user || res.data;
                if (!user.is_admin) {
                    navigate('/dashboard', { replace: true });
                    return;
                }
                return Promise.all([
                    api.get('/admin/stats'),
                    api.get('/admin/settings'),
                ]);
            })
            .then((results) => {
                if (!results) return;
                const [statsRes, settingsRes] = results;
                setStats(statsRes.data.data || statsRes.data);
                const s = settingsRes.data.data || settingsRes.data;
                setSettings({
                    provider: s.provider || 'anthropic',
                    model: s.model || '',
                    max_tokens: s.max_tokens ?? '',
                    rate_limit: s.rate_limit ?? '',
                });
            })
            .catch(() => {})
            .finally(() => setLoading(false));
    }, [navigate]);

    const handleSave = async () => {
        setSaving(true);
        try {
            await api.put('/admin/settings', settings);
            setToast({ message: 'Settings saved successfully', type: 'success' });
        } catch {
            setToast({ message: 'Failed to save settings', type: 'error' });
        } finally {
            setSaving(false);
        }
    };

    if (loading) {
        return (
            <AppLayout activePage="admin">
                <div className="p-8 flex items-center justify-center min-h-[60vh]">
                    <span className="text-primary font-mono text-sm">Loading admin panel...</span>
                </div>
            </AppLayout>
        );
    }

    const totalCost = stats?.total_cost != null
        ? `$${Number(stats.total_cost).toFixed(4)}`
        : '$0.0000';

    return (
        <AppLayout activePage="admin">
            <div className="p-8 max-w-5xl">
                <div className="flex items-center gap-2 mb-6">
                    <span className="material-symbols-outlined text-primary text-xl">admin_panel_settings</span>
                    <h1 className="text-on-surface text-xl font-bold">Admin Panel</h1>
                </div>

                {/* Stats Section */}
                <div className="grid grid-cols-2 md:grid-cols-3 gap-4">
                    <StatCard icon="people" value={stats?.users ?? 0} label="Users" />
                    <StatCard icon="folder" value={stats?.projects ?? 0} label="Projects" />
                    <StatCard icon="smart_toy" value={stats?.generations ?? 0} label="Generations" />
                    <StatCard icon="payments" value={totalCost} label="Total Cost" />
                    <StatCard icon="today" value={stats?.generations_today ?? 0} label="Generations Today" />
                    <StatCard
                        icon="settings"
                        value={
                            <span className="text-base font-mono">
                                {settings.provider}/{settings.model ? settings.model.split('/').pop() : '—'}
                            </span>
                        }
                        label="Active Provider + Model"
                    />
                </div>

                {/* AI Settings Section */}
                <div className="mt-8">
                    <div className="bg-surface-container rounded-xl p-5 border border-outline-variant/5">
                        <div className="flex items-center gap-2 mb-5">
                            <span className="material-symbols-outlined text-primary text-xl">tune</span>
                            <h2 className="text-on-surface font-bold text-lg">AI Settings</h2>
                        </div>

                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label className="block text-on-surface-variant text-sm font-label mb-1.5">
                                    Provider
                                </label>
                                <select
                                    value={settings.provider}
                                    onChange={(e) =>
                                        setSettings((prev) => ({ ...prev, provider: e.target.value }))
                                    }
                                    className="w-full bg-surface-container-lowest font-mono text-on-surface px-4 py-3 rounded-md border border-outline-variant/15 outline-none transition-all focus:border-primary/50 focus:ring-2 focus:ring-primary-container/5"
                                >
                                    <option value="anthropic">Anthropic</option>
                                    <option value="gemini">Gemini</option>
                                </select>
                            </div>

                            <Input
                                label="Model"
                                value={settings.model}
                                onChange={(e) =>
                                    setSettings((prev) => ({ ...prev, model: e.target.value }))
                                }
                                placeholder="claude-sonnet-4-6-20250320"
                            />

                            <Input
                                label="Max Tokens"
                                type="number"
                                value={settings.max_tokens}
                                onChange={(e) =>
                                    setSettings((prev) => ({ ...prev, max_tokens: e.target.value }))
                                }
                                placeholder="8192"
                            />

                            <Input
                                label="Max generations/hour"
                                type="number"
                                value={settings.rate_limit}
                                onChange={(e) =>
                                    setSettings((prev) => ({ ...prev, rate_limit: e.target.value }))
                                }
                                placeholder="60"
                            />
                        </div>

                        <div className="mt-5">
                            <Button onClick={handleSave} loading={saving}>
                                Save Settings
                            </Button>
                        </div>
                    </div>
                </div>
            </div>

            {toast && (
                <Toast
                    message={toast.message}
                    type={toast.type}
                    onDismiss={() => setToast(null)}
                />
            )}
        </AppLayout>
    );
}
