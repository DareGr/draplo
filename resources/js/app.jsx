import { createRoot } from 'react-dom/client';
import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';

function App() {
    return (
        <div className="min-h-screen flex items-center justify-center">
            <h1 className="text-4xl font-extrabold text-primary tracking-tight">Draplo</h1>
        </div>
    );
}

const root = createRoot(document.getElementById('app'));
root.render(
    <BrowserRouter>
        <Routes>
            <Route path="*" element={<App />} />
        </Routes>
    </BrowserRouter>
);
