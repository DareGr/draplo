import { useEffect, useState } from 'react';
import api from '../api';
import AppLayout from '../components/AppLayout';
import Toast from '../components/Toast';

export default function Settings() {
    const [user, setUser] = useState(null);
    const [servers, setServers] = useState([]);
    const [loading, setLoading] = useState(true);
    const [toast, setToast] = useState(null);
    const [checkingHealth, setCheckingHealth] = useState({});

    useEffect(() => {
        Promise.all([
            api.get('/auth/me'),
            api.get('/servers').catch(() => ({ data: { data: [] } })),
        ])
            .then(([userRes, serversRes]) => {
                setUser(userRes.data.user || userRes.data);
                setServers(serversRes.data.data || serversRes.data || []);
            })
            .catch(() => {})
            .finally(() => setLoading(false));
    }, []);

    const checkHealth = async (serverId) => {
        setCheckingHealth((prev) => ({ ...prev, [serverId]: true }));
        try {
            await api.get(`/servers/${serverId}/health`);
            setToast({ message: 'Server is healthy', type: 'success' });
        } catch {
            setToast({ message: 'Server health check failed', type: 'error' });
        } finally {
            setCheckingHealth((prev) => ({ ...prev, [serverId]: false }));
        }
    };

    if (loading) {
        return (
            <AppLayout activePage="settings">
                <div className="p-8 flex items-center justify-center min-h-[60vh]">
                    <span className="text-primary font-mono text-sm">Loading settings...</span>
                </div>
            </AppLayout>
        );
    }

    const planLabel = user?.plan || 'free';
    const userInitial = user?.name ? user.name.charAt(0).toUpperCase() : '?';

    return (
        <AppLayout activePage="settings">
            <div className="p-8 max-w-3xl space-y-6">
                {/* Profile Card */}
                <div className="bg-surface-container rounded-xl p-5 border border-outline-variant/5">
                    <div className="flex items-center gap-4 mb-5">
                        {user?.avatar_url ? (
                            <img
                                src={user.avatar_url}
                                alt={user.name}
                                className="w-16 h-16 rounded-full"
                            />
                        ) : (
                            <div className="w-16 h-16 rounded-full bg-surface-container-highest flex items-center justify-center">
                                <span className="text-2xl font-bold text-on-surface-variant">{userInitial}</span>
                            </div>
                        )}
                        <div>
                            <h2 className="text-on-surface text-lg font-bold">{user?.name || 'Unknown'}</h2>
                            <p className="font-mono text-sm text-on-surface-variant">{user?.email || ''}</p>
                        </div>
                    </div>

                    <div className="space-y-3">
                        <div>
                            <span className="font-label text-[11px] uppercase tracking-widest text-on-surface-variant">
                                Name
                            </span>
                            <p className="font-mono text-sm text-on-surface mt-0.5">{user?.name || '—'}</p>
                        </div>
                        <div>
                            <span className="font-label text-[11px] uppercase tracking-widest text-on-surface-variant">
                                Email
                            </span>
                            <p className="font-mono text-sm text-on-surface mt-0.5">{user?.email || '—'}</p>
                        </div>
                        <div>
                            <span className="font-label text-[11px] uppercase tracking-widest text-on-surface-variant">
                                GitHub Username
                            </span>
                            <p className="font-mono text-sm text-on-surface mt-0.5">{user?.github_username || '—'}</p>
                        </div>
                    </div>
                </div>

                {/* Plan Card */}
                <div className="bg-surface-container rounded-xl p-5 border border-outline-variant/5">
                    <div className="flex items-center gap-2 mb-3">
                        <span className="material-symbols-outlined text-primary text-xl">credit_card</span>
                        <h3 className="text-on-surface font-bold">Plan</h3>
                    </div>
                    <span className="inline-flex items-center px-3 py-1 bg-primary/15 text-primary rounded text-sm font-bold font-mono capitalize">
                        {planLabel}
                    </span>
                    <div className="mt-3">
                        <a
                            href="/#pricing"
                            className="text-primary text-sm font-medium hover:underline"
                        >
                            View Pricing
                        </a>
                    </div>
                </div>

                {/* GitHub Connection Card */}
                <div className="bg-surface-container rounded-xl p-5 border border-outline-variant/5">
                    <div className="flex items-center gap-2 mb-3">
                        <span className="material-symbols-outlined text-primary text-xl">link</span>
                        <h3 className="text-on-surface font-bold">GitHub Connection</h3>
                    </div>
                    <div className="flex items-center gap-2">
                        <span className="w-2.5 h-2.5 rounded-full bg-green-400" />
                        <span className="text-on-surface text-sm">Connected</span>
                        <span className="font-mono text-sm text-on-surface-variant">
                            {user?.github_username || ''}
                        </span>
                    </div>
                    <div className="mt-3">
                        <a
                            href="/auth/github"
                            className="text-primary text-sm font-medium hover:underline"
                        >
                            Reconnect GitHub
                        </a>
                    </div>
                </div>

                {/* Server Connections Card */}
                <div className="bg-surface-container rounded-xl p-5 border border-outline-variant/5">
                    <div className="flex items-center gap-2 mb-3">
                        <span className="material-symbols-outlined text-primary text-xl">dns</span>
                        <h3 className="text-on-surface font-bold">Server Connections</h3>
                    </div>

                    {servers.length === 0 ? (
                        <div className="text-center py-6">
                            <span className="material-symbols-outlined text-3xl text-outline mb-2 block">cloud_off</span>
                            <p className="text-on-surface-variant text-sm">
                                No servers connected. Deploy a project to add your first server.
                            </p>
                        </div>
                    ) : (
                        <div className="space-y-3">
                            {servers.map((server) => (
                                <div
                                    key={server.id}
                                    className="flex items-center justify-between bg-surface-container-lowest rounded-lg px-4 py-3"
                                >
                                    <div className="flex items-center gap-3 min-w-0">
                                        <span
                                            className={`w-2 h-2 rounded-full shrink-0 ${
                                                server.status === 'active' ? 'bg-green-400' : 'bg-outline'
                                            }`}
                                        />
                                        <div className="min-w-0">
                                            <p className="text-on-surface text-sm font-medium truncate">
                                                {server.name}
                                            </p>
                                            <p className="font-mono text-[11px] text-on-surface-variant">
                                                {server.ip || server.ip_address || '—'}
                                            </p>
                                        </div>
                                    </div>
                                    <div className="flex items-center gap-3 shrink-0">
                                        <span className="inline-flex items-center px-2 py-0.5 bg-primary/10 text-primary text-[10px] font-mono rounded capitalize">
                                            {server.provider || '—'}
                                        </span>
                                        <button
                                            onClick={() => checkHealth(server.id)}
                                            disabled={checkingHealth[server.id]}
                                            className="text-primary text-xs font-medium hover:underline disabled:opacity-50"
                                        >
                                            {checkingHealth[server.id] ? 'Checking...' : 'Health Check'}
                                        </button>
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </div>

                {/* Danger Zone Card */}
                <div className="bg-surface-container rounded-xl p-5 border border-error/20">
                    <div className="flex items-center gap-2 mb-3">
                        <span className="material-symbols-outlined text-tertiary-container text-xl">warning</span>
                        <h3 className="text-on-surface font-bold">Danger Zone</h3>
                    </div>
                    <p className="text-on-surface-variant text-sm mb-4">
                        Permanently delete your account and all associated data. This action cannot be undone.
                    </p>
                    <button
                        disabled
                        className="px-5 py-2 bg-tertiary-container/20 text-tertiary rounded-md text-sm font-bold opacity-50 cursor-not-allowed"
                        title="Coming soon"
                    >
                        Delete Account
                    </button>
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
