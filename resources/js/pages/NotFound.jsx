import { Link } from 'react-router-dom';
import AppLayout from '../components/AppLayout';

export default function NotFound() {
    return (
        <AppLayout activePage="">
            <div className="flex flex-col items-center justify-center h-[calc(100vh-4rem)]">
                <span className="material-symbols-outlined text-6xl text-outline mb-4">search_off</span>
                <h1 className="text-3xl font-bold text-white mb-2">Page not found</h1>
                <p className="text-on-surface-variant mb-6">The page you're looking for doesn't exist.</p>
                <Link
                    to="/templates"
                    className="px-6 py-2.5 bg-gradient-to-r from-primary to-primary-container text-on-primary font-bold rounded-md"
                >
                    Back to Templates
                </Link>
            </div>
        </AppLayout>
    );
}
