import { useState, useEffect, useRef } from 'react';
import api from '../../api';
import Button from '../../components/Button';
import Toast from '../../components/Toast';

const DEPLOY_STEPS = [
    { key: 'creating_app', label: 'Creating app', icon: 'apps' },
    { key: 'creating_database', label: 'Creating database', icon: 'database' },
    { key: 'setting_env', label: 'Setting environment', icon: 'tune' },
    { key: 'building', label: 'Building', icon: 'build' },
    { key: 'deploying', label: 'Deploying', icon: 'rocket_launch' },
    { key: 'live', label: 'Live', icon: 'check_circle' },
];

const PROVIDER_STYLES = {
    hetzner: { bg: 'bg-blue-500/10', text: 'text-blue-400', label: 'Hetzner' },
    manual: { bg: 'bg-outline-variant/10', text: 'text-outline', label: 'Manual' },
};

function StepItem({ step, status }) {
    const isDone = status === 'done';
    const isActive = status === 'active';

    return (
        <div className="flex items-start gap-3 py-2">
            <div className="flex flex-col items-center">
                <div
                    className={`w-8 h-8 rounded-full flex items-center justify-center transition-all ${
                        isDone
                            ? 'bg-green-500/15'
                            : isActive
                            ? 'bg-primary/15 animate-pulse'
                            : 'bg-surface-container-highest'
                    }`}
                >
                    <span
                        className={`material-symbols-outlined text-[18px] ${
                            isDone ? 'text-green-500' : isActive ? 'text-primary' : 'text-outline'
                        }`}
                    >
                        {isDone ? 'check_circle' : step.icon}
                    </span>
                </div>
            </div>
            <div className="pt-1">
                <span
                    className={`text-sm font-medium ${
                        isDone ? 'text-green-500' : isActive ? 'text-on-surface' : 'text-outline'
                    }`}
                >
                    {step.label}
                </span>
            </div>
        </div>
    );
}

export default function DeployProgress({ projectId, server, initialStatus }) {
    const [deploying, setDeploying] = useState(false);
    const [deployStatus, setDeployStatus] = useState(initialStatus || null);
    const [logs, setLogs] = useState([]);
    const [toast, setToast] = useState(null);
    const pollRef = useRef(null);
    const logEndRef = useRef(null);

    const isDeployed = deployStatus?.status === 'live' || deployStatus?.status === 'deployed';
    const isFailed = deployStatus?.status === 'failed';
    const isInProgress = deployStatus && !isDeployed && !isFailed && deployStatus.status !== 'idle';

    useEffect(() => {
        return () => {
            if (pollRef.current) clearInterval(pollRef.current);
        };
    }, []);

    useEffect(() => {
        if (logEndRef.current) {
            logEndRef.current.scrollIntoView({ behavior: 'smooth' });
        }
    }, [logs]);

    const addLog = (message) => {
        const timestamp = new Date().toLocaleTimeString('en-US', { hour12: false });
        setLogs((prev) => [...prev, `[${timestamp}] ${message}`]);
    };

    const handleDeploy = async () => {
        if (!window.confirm('Deploy this project to your server? This will create the app and database.')) return;
        setDeploying(true);
        setLogs([]);
        addLog('Initiating deployment...');

        try {
            const { data } = await api.post(`/projects/${projectId}/deploy`, {
                server_id: server.id,
            });
            setDeployStatus(data);
            addLog(`Deployment started (ID: ${data.deployment_id || data.id})`);

            // Poll for progress
            pollRef.current = setInterval(async () => {
                try {
                    const { data: status } = await api.get(`/projects/${projectId}/deploy/status`);
                    setDeployStatus(status);
                    if (status.message) {
                        addLog(status.message);
                    }
                    if (status.status === 'live' || status.status === 'deployed') {
                        clearInterval(pollRef.current);
                        pollRef.current = null;
                        addLog(`Deployment complete. App is live at ${status.url}`);
                        setDeploying(false);
                    } else if (status.status === 'failed') {
                        clearInterval(pollRef.current);
                        pollRef.current = null;
                        addLog(`Deployment failed: ${status.error || 'Unknown error'}`);
                        setDeploying(false);
                    }
                } catch {
                    // ignore poll errors
                }
            }, 3000);
        } catch (err) {
            addLog(`Error: ${err.response?.data?.message || 'Failed to start deployment'}`);
            setToast({ message: err.response?.data?.message || 'Failed to start deployment', type: 'error' });
            setDeploying(false);
        }
    };

    const getStepStatus = (stepKey) => {
        if (!deployStatus || !deployStatus.step) return 'pending';
        const currentIdx = DEPLOY_STEPS.findIndex((s) => s.key === deployStatus.step);
        const stepIdx = DEPLOY_STEPS.findIndex((s) => s.key === stepKey);
        if (isDeployed) return 'done';
        if (stepIdx < currentIdx) return 'done';
        if (stepIdx === currentIdx) return 'active';
        return 'pending';
    };

    const providerStyle = PROVIDER_STYLES[server.provider] || PROVIDER_STYLES.manual;

    return (
        <div>
            <div className="mb-8">
                <span className="text-primary font-mono text-xs tracking-widest uppercase">Deploy</span>
                <h2 className="text-3xl font-extrabold tracking-tight text-white font-headline mt-2">
                    Deploy to Server
                </h2>
            </div>

            {/* Server info card */}
            <div className="bg-surface-container rounded-xl p-5 border border-outline-variant/10 mb-6">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-3">
                        <span className="material-symbols-outlined text-xl text-primary">dns</span>
                        <div>
                            <h3 className="text-on-surface font-bold">{server.name}</h3>
                            {server.ip_address && (
                                <span className="font-mono text-xs text-outline">{server.ip_address}</span>
                            )}
                        </div>
                    </div>
                    <div className="flex items-center gap-3">
                        <span
                            className={`${providerStyle.bg} ${providerStyle.text} text-xs font-mono px-2.5 py-1 rounded-md`}
                        >
                            {providerStyle.label}
                        </span>
                        <div className="flex items-center gap-1.5">
                            <div
                                className={`w-2 h-2 rounded-full ${
                                    server.status === 'active' ? 'bg-green-500' : 'bg-amber-500'
                                }`}
                            />
                            <span className="font-mono text-[11px] text-on-surface-variant capitalize">
                                {server.status}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <div className="grid md:grid-cols-2 gap-6">
                {/* Left: Steps + deploy button */}
                <div className="bg-surface-container rounded-xl p-6 border border-outline-variant/10">
                    <h4 className="text-sm font-label text-on-surface-variant mb-4">Deployment Steps</h4>
                    <div className="space-y-1">
                        {DEPLOY_STEPS.map((step) => (
                            <StepItem key={step.key} step={step} status={getStepStatus(step.key)} />
                        ))}
                    </div>

                    <div className="mt-6">
                        {isDeployed ? (
                            <div className="space-y-3">
                                <div className="flex items-center gap-2">
                                    <div className="w-2.5 h-2.5 rounded-full bg-green-500" />
                                    <span className="text-green-500 font-bold text-sm">Live</span>
                                </div>
                                {deployStatus.url && (
                                    <a
                                        href={deployStatus.url}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="block font-mono text-sm text-primary hover:text-primary-container transition-colors truncate"
                                    >
                                        {deployStatus.url}
                                    </a>
                                )}
                                {deployStatus.url && (
                                    <Button
                                        variant="primary"
                                        onClick={() => window.open(deployStatus.url, '_blank')}
                                        className="w-full"
                                    >
                                        <span className="inline-flex items-center gap-1.5">
                                            <span className="material-symbols-outlined text-[18px]">open_in_new</span>
                                            Open App
                                        </span>
                                    </Button>
                                )}
                            </div>
                        ) : (
                            <Button
                                variant="primary"
                                onClick={handleDeploy}
                                loading={deploying}
                                disabled={isInProgress}
                                className="w-full"
                            >
                                <span className="inline-flex items-center gap-1.5">
                                    <span className="material-symbols-outlined text-[18px]">rocket_launch</span>
                                    {isFailed ? 'Retry Deploy' : 'Deploy'}
                                </span>
                            </Button>
                        )}
                    </div>
                </div>

                {/* Right: Terminal log */}
                <div className="bg-surface-container rounded-xl p-6 border border-outline-variant/10">
                    <h4 className="text-sm font-label text-on-surface-variant mb-4">Deploy Log</h4>
                    <div className="bg-surface-container-lowest font-mono text-xs p-4 rounded-xl h-48 overflow-y-auto text-on-surface-variant">
                        {logs.length === 0 ? (
                            <span className="text-outline">Waiting for deployment...</span>
                        ) : (
                            logs.map((line, i) => (
                                <div key={i} className="py-0.5">
                                    {line}
                                </div>
                            ))
                        )}
                        <div ref={logEndRef} />
                    </div>
                </div>
            </div>

            {toast && <Toast message={toast.message} type={toast.type} onDismiss={() => setToast(null)} />}
        </div>
    );
}
