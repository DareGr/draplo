import { createRoot } from 'react-dom/client';
import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import { useEffect, useState } from 'react';
import { ensureAuth } from './api';
import AppLayout from './components/AppLayout';

function TemplateLibrary() {
    return (
        <div className="p-12">
            <h1 className="text-4xl font-extrabold text-white tracking-tight font-headline">Template Library</h1>
            <p className="text-on-surface-variant mt-2">Placeholder -- will be built in Task 15</p>
        </div>
    );
}

function ProjectList() {
    return (
        <div className="p-12">
            <h1 className="text-4xl font-extrabold text-white tracking-tight font-headline">Projects</h1>
            <p className="text-on-surface-variant mt-2">Placeholder -- will be built in Task 16</p>
        </div>
    );
}

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
            <Route path="/templates" element={
                <AppLayout activePage="library">
                    <TemplateLibrary />
                </AppLayout>
            } />
            <Route path="/projects" element={
                <AppLayout activePage="dashboard">
                    <ProjectList />
                </AppLayout>
            } />
            <Route path="*" element={<Navigate to="/templates" replace />} />
        </Routes>
    );
}

const root = createRoot(document.getElementById('app'));
root.render(
    <BrowserRouter>
        <App />
    </BrowserRouter>
);
