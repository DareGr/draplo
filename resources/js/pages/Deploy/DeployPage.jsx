import { useState, useEffect } from 'react';
import { useParams } from 'react-router-dom';
import api from '../../api';
import AppLayout from '../../components/AppLayout';
import ServerSetup from './ServerSetup';
import DeployProgress from './DeployProgress';

export default function DeployPage() {
    const { projectId } = useParams();
    const [loading, setLoading] = useState(true);
    const [server, setServer] = useState(null);
    const [deployStatus, setDeployStatus] = useState(null);

    useEffect(() => {
        async function init() {
            try {
                // Check for active servers
                const { data: servers } = await api.get('/servers');
                const activeServer = servers.find((s) => s.status === 'active');

                if (activeServer) {
                    setServer(activeServer);
                }

                // Check current deploy status
                try {
                    const { data: status } = await api.get(`/projects/${projectId}/deploy/status`);
                    if (status && status.status && status.status !== 'idle') {
                        setDeployStatus(status);
                        // If deploy status has a server and we didn't find one yet, use it
                        if (!activeServer && status.server) {
                            setServer(status.server);
                        }
                    }
                } catch {
                    // No deploy status yet, that's fine
                }
            } catch {
                // No servers yet, show setup
            } finally {
                setLoading(false);
            }
        }

        init();
    }, [projectId]);

    const handleServerConnected = (connectedServer) => {
        setServer(connectedServer);
    };

    if (loading) {
        return (
            <AppLayout activePage="deployments">
                <div className="p-12 flex items-center justify-center">
                    <div className="text-primary text-lg font-mono">Loading...</div>
                </div>
            </AppLayout>
        );
    }

    return (
        <AppLayout activePage="deployments">
            <div className="p-12 pb-20">
                <section className="max-w-4xl mx-auto">
                    {server ? (
                        <DeployProgress
                            projectId={projectId}
                            server={server}
                            initialStatus={deployStatus}
                        />
                    ) : (
                        <ServerSetup onServerConnected={handleServerConnected} />
                    )}
                </section>
            </div>
        </AppLayout>
    );
}
