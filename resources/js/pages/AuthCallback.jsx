import { useEffect } from 'react';
import { useNavigate } from 'react-router-dom';

export default function AuthCallback() {
    const navigate = useNavigate();

    useEffect(() => {
        const hash = window.location.hash;
        const match = hash.match(/token=([^&]+)/);

        if (match) {
            localStorage.setItem('auth_token', match[1]);
            navigate('/templates', { replace: true });
        } else {
            // No token found — redirect to GitHub login
            window.location.href = '/auth/github';
        }
    }, [navigate]);

    return (
        <div className="min-h-screen flex items-center justify-center bg-background">
            <div className="text-primary font-mono">Authenticating...</div>
        </div>
    );
}
