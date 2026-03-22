import { useState, useEffect, useRef } from 'react';
import api from '../../api';
import Button from '../../components/Button';
import Input from '../../components/Input';
import Toast from '../../components/Toast';

const SERVER_TYPES = [
    { value: 'cx22', label: 'CX22 — 2 vCPU / 4 GB RAM' },
    { value: 'cx32', label: 'CX32 — 4 vCPU / 8 GB RAM' },
    { value: 'cx42', label: 'CX42 — 8 vCPU / 16 GB RAM' },
];

const STATUS_STEPS = ['pending', 'provisioning', 'installing'];

function StatusStepper({ currentStatus }) {
    const currentIdx = STATUS_STEPS.indexOf(currentStatus);

    return (
        <div className="flex items-center gap-3 mt-4">
            {STATUS_STEPS.map((step, i) => {
                const isDone = i < currentIdx;
                const isActive = i === currentIdx;
                return (
                    <div key={step} className="flex items-center gap-2">
                        <div
                            className={`w-2.5 h-2.5 rounded-full transition-all ${
                                isDone
                                    ? 'bg-green-500'
                                    : isActive
                                    ? 'bg-amber-500 animate-pulse'
                                    : 'bg-outline-variant/30'
                            }`}
                        />
                        <span
                            className={`font-mono text-xs capitalize ${
                                isDone
                                    ? 'text-green-500'
                                    : isActive
                                    ? 'text-amber-500'
                                    : 'text-outline'
                            }`}
                        >
                            {step}
                        </span>
                        {i < STATUS_STEPS.length - 1 && (
                            <div className="w-6 h-px bg-outline-variant/20" />
                        )}
                    </div>
                );
            })}
        </div>
    );
}

function HetznerCard({ onServerConnected }) {
    const [apiKey, setApiKey] = useState('');
    const [serverName, setServerName] = useState('');
    const [serverType, setServerType] = useState('cx22');
    const [creating, setCreating] = useState(false);
    const [server, setServer] = useState(null);
    const [coolifyUrl, setCoolifyUrl] = useState('');
    const [coolifyKey, setCoolifyKey] = useState('');
    const [verifying, setVerifying] = useState(false);
    const [toast, setToast] = useState(null);
    const pollRef = useRef(null);

    useEffect(() => {
        return () => {
            if (pollRef.current) clearInterval(pollRef.current);
        };
    }, []);

    const handleCreate = async () => {
        if (!apiKey || !serverName) return;
        setCreating(true);
        try {
            const { data } = await api.post('/servers', {
                provider: 'hetzner',
                name: serverName,
                server_type: serverType,
                api_key: apiKey,
            });
            setServer(data);
            setCoolifyUrl(`http://${data.ip_address}:8000`);

            // Poll for status updates
            pollRef.current = setInterval(async () => {
                try {
                    const { data: updated } = await api.get(`/servers/${data.id}`);
                    setServer(updated);
                    if (updated.ip_address) {
                        setCoolifyUrl(`http://${updated.ip_address}:8000`);
                    }
                    if (updated.status === 'active' || updated.status === 'failed') {
                        clearInterval(pollRef.current);
                        pollRef.current = null;
                    }
                } catch {
                    // ignore poll errors
                }
            }, 5000);
        } catch (err) {
            setToast({ message: err.response?.data?.message || 'Failed to create server', type: 'error' });
        } finally {
            setCreating(false);
        }
    };

    const handleVerify = async () => {
        if (!coolifyUrl || !coolifyKey) return;
        setVerifying(true);
        try {
            const { data: health } = await api.get(`/servers/${server.id}/health`, {
                params: { coolify_url: coolifyUrl, coolify_api_key: coolifyKey },
            });
            if (health.connected) {
                setToast({ message: 'Coolify connected successfully', type: 'success' });
                onServerConnected({ ...server, status: 'active', coolify_url: coolifyUrl });
            } else {
                setToast({ message: 'Could not connect to Coolify. Check URL and API key.', type: 'error' });
            }
        } catch (err) {
            setToast({ message: err.response?.data?.message || 'Verification failed', type: 'error' });
        } finally {
            setVerifying(false);
        }
    };

    const showCoolifySetup = server && (server.status === 'installing' || server.status === 'active');

    return (
        <div className="bg-surface-container rounded-xl p-6 border border-outline-variant/10">
            <div className="flex items-center gap-3 mb-5">
                <span className="material-symbols-outlined text-2xl text-primary">cloud</span>
                <h3 className="text-lg font-bold text-on-surface">Create with Hetzner</h3>
            </div>

            {!server ? (
                <div className="space-y-4">
                    <Input
                        label="Hetzner API Key"
                        type="password"
                        value={apiKey}
                        onChange={(e) => setApiKey(e.target.value)}
                        placeholder="Enter your Hetzner API key"
                        required
                    />
                    <Input
                        label="Server Name"
                        value={serverName}
                        onChange={(e) => setServerName(e.target.value)}
                        placeholder="my-saas-server"
                        required
                    />
                    <div>
                        <label className="block text-on-surface-variant text-sm font-label mb-1.5">
                            Server Type<span className="text-tertiary ml-1">*</span>
                        </label>
                        <select
                            value={serverType}
                            onChange={(e) => setServerType(e.target.value)}
                            className="w-full bg-surface-container-lowest font-mono text-on-surface px-4 py-3 rounded-md border border-outline-variant/15 outline-none transition-all focus:border-primary/50 focus:ring-2 focus:ring-primary-container/5"
                        >
                            {SERVER_TYPES.map((t) => (
                                <option key={t.value} value={t.value}>
                                    {t.label}
                                </option>
                            ))}
                        </select>
                    </div>
                    <Button variant="primary" onClick={handleCreate} loading={creating} className="w-full">
                        <span className="inline-flex items-center gap-1.5">
                            <span className="material-symbols-outlined text-[18px]">cloud_upload</span>
                            Create Server
                        </span>
                    </Button>
                </div>
            ) : (
                <div className="space-y-4">
                    <div className="bg-surface-container-lowest rounded-lg p-4">
                        <div className="flex items-center justify-between mb-2">
                            <span className="font-mono text-sm text-on-surface">{server.name}</span>
                            {server.ip_address && (
                                <span className="font-mono text-xs text-outline">{server.ip_address}</span>
                            )}
                        </div>
                        <StatusStepper currentStatus={server.status} />
                    </div>

                    {showCoolifySetup && (
                        <div className="space-y-4">
                            <div className="bg-surface-container-lowest rounded-lg p-4 border border-primary/10">
                                <p className="text-sm text-on-surface-variant mb-3">
                                    Coolify is installing on your server. To complete setup:
                                </p>
                                <ol className="text-sm text-on-surface-variant space-y-1.5 list-decimal list-inside">
                                    <li>Wait 3-5 minutes for installation</li>
                                    <li>
                                        Visit{' '}
                                        <a
                                            href={coolifyUrl}
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            className="font-mono text-primary hover:text-primary-container transition-colors"
                                        >
                                            {coolifyUrl}
                                        </a>
                                    </li>
                                    <li>Complete Coolify initial setup</li>
                                    <li>Copy API key from Coolify settings</li>
                                    <li>Paste it below</li>
                                </ol>
                            </div>

                            <Input
                                label="Coolify URL"
                                value={coolifyUrl}
                                onChange={(e) => setCoolifyUrl(e.target.value)}
                                placeholder="http://your-server-ip:8000"
                            />
                            <Input
                                label="Coolify API Key"
                                type="password"
                                value={coolifyKey}
                                onChange={(e) => setCoolifyKey(e.target.value)}
                                placeholder="Enter Coolify API key"
                                required
                            />
                            <Button variant="primary" onClick={handleVerify} loading={verifying} className="w-full">
                                <span className="inline-flex items-center gap-1.5">
                                    <span className="material-symbols-outlined text-[18px]">verified</span>
                                    Verify &amp; Connect
                                </span>
                            </Button>
                        </div>
                    )}
                </div>
            )}

            {toast && <Toast message={toast.message} type={toast.type} onDismiss={() => setToast(null)} />}
        </div>
    );
}

function ManualCard({ onServerConnected }) {
    const [coolifyUrl, setCoolifyUrl] = useState('');
    const [coolifyKey, setCoolifyKey] = useState('');
    const [serverName, setServerName] = useState('');
    const [connecting, setConnecting] = useState(false);
    const [toast, setToast] = useState(null);

    const handleConnect = async () => {
        if (!coolifyUrl || !coolifyKey || !serverName) return;
        setConnecting(true);
        try {
            const { data } = await api.post('/servers', {
                provider: 'manual',
                name: serverName,
                coolify_url: coolifyUrl,
                coolify_api_key: coolifyKey,
            });
            setToast({ message: 'Server connected successfully', type: 'success' });
            setTimeout(() => onServerConnected(data), 500);
        } catch (err) {
            setToast({ message: err.response?.data?.message || 'Failed to connect server', type: 'error' });
        } finally {
            setConnecting(false);
        }
    };

    return (
        <div className="bg-surface-container rounded-xl p-6 border border-outline-variant/10">
            <div className="flex items-center gap-3 mb-5">
                <span className="material-symbols-outlined text-2xl text-primary">link</span>
                <h3 className="text-lg font-bold text-on-surface">Connect Existing Coolify</h3>
            </div>

            <div className="space-y-4">
                <Input
                    label="Coolify URL"
                    value={coolifyUrl}
                    onChange={(e) => setCoolifyUrl(e.target.value)}
                    placeholder="https://coolify.myserver.com"
                    required
                />
                <Input
                    label="Coolify API Key"
                    type="password"
                    value={coolifyKey}
                    onChange={(e) => setCoolifyKey(e.target.value)}
                    placeholder="Enter Coolify API key"
                    required
                />
                <Input
                    label="Server Name"
                    value={serverName}
                    onChange={(e) => setServerName(e.target.value)}
                    placeholder="my-production-server"
                    required
                />
                <Button variant="secondary" onClick={handleConnect} loading={connecting} className="w-full">
                    <span className="inline-flex items-center gap-1.5">
                        <span className="material-symbols-outlined text-[18px]">link</span>
                        Connect
                    </span>
                </Button>
            </div>

            {toast && <Toast message={toast.message} type={toast.type} onDismiss={() => setToast(null)} />}
        </div>
    );
}

export default function ServerSetup({ onServerConnected }) {
    return (
        <div>
            <div className="mb-8">
                <span className="text-primary font-mono text-xs tracking-widest uppercase">Deploy</span>
                <h2 className="text-3xl font-extrabold tracking-tight text-white font-headline mt-2">
                    Connect a Server
                </h2>
                <p className="text-on-surface-variant mt-2 text-sm">
                    Create a new server on Hetzner or connect an existing Coolify instance.
                </p>
            </div>
            <div className="grid md:grid-cols-2 gap-6">
                <HetznerCard onServerConnected={onServerConnected} />
                <ManualCard onServerConnected={onServerConnected} />
            </div>
        </div>
    );
}
