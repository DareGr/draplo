import { createRoot } from 'react-dom/client';
import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import { useEffect, useState } from 'react';
import { ensureAuth } from './api';
import TemplateLibrary from './pages/TemplateLibrary';
import ProjectList from './pages/ProjectList';
import WizardLayout from './pages/Wizard/WizardLayout';
import PreviewLayout from './pages/Preview/PreviewLayout';
import DeployPage from './pages/Deploy/DeployPage';
import AuthCallback from './pages/AuthCallback';
import Dashboard from './pages/Dashboard';
import Settings from './pages/Settings';
import Admin from './pages/Admin';

function App() {
    const [ready, setReady] = useState(false);

    useEffect(() => {
        ensureAuth().then(() => setReady(true));
    }, []);

    if (!ready) {
        return (
            <div className="min-h-screen flex items-center justify-center bg-background">
                <div className="text-primary text-lg font-mono">Initializing...</div>
            </div>
        );
    }

    return (
        <Routes>
            <Route path="/templates" element={<TemplateLibrary />} />
            <Route path="/projects" element={<ProjectList />} />
            <Route path="/wizard/:projectId" element={<WizardLayout />} />
            <Route path="/projects/:projectId/preview" element={<PreviewLayout />} />
            <Route path="/projects/:projectId/deploy" element={<DeployPage />} />
            <Route path="/auth/callback" element={<AuthCallback />} />
            <Route path="/dashboard" element={<Dashboard />} />
            <Route path="/settings" element={<Settings />} />
            <Route path="/admin" element={<Admin />} />
            <Route path="*" element={<Navigate to="/dashboard" replace />} />
        </Routes>
    );
}

const root = createRoot(document.getElementById('app'));
root.render(
    <BrowserRouter>
        <App />
    </BrowserRouter>
);
