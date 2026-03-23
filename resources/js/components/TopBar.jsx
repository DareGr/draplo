import { NavLink } from 'react-router-dom';

const topNavItems = [
    { label: 'Templates', to: '/templates' },
    { label: 'Community', to: '/#community' },
    { label: 'Docs', to: '/docs' },
    { label: 'OS', to: '/open-source' },
];

export default function TopBar() {
    return (
        <header className="fixed top-0 left-0 right-0 h-16 z-40 bg-background/80 backdrop-blur-xl border-b border-primary/15">
            <div className="ml-64 h-full flex items-center justify-between px-6">
                {/* Left side nav links */}
                <nav className="flex items-center gap-6">
                    {topNavItems.map((item) => (
                        <NavLink
                            key={item.to}
                            to={item.to}
                            className="text-sm text-on-surface-variant hover:text-on-surface transition-colors font-label"
                        >
                            {item.label}
                        </NavLink>
                    ))}
                </nav>

                {/* Right side actions */}
                <div className="flex items-center gap-4">
                    <NavLink
                        to="/login"
                        className="text-sm text-on-surface-variant hover:text-on-surface transition-colors font-label"
                    >
                        Sign In
                    </NavLink>
                    <NavLink
                        to="/wizard"
                        className="px-4 py-2 text-sm font-bold rounded-md bg-gradient-to-r from-tertiary-container to-tertiary text-on-tertiary-container transition-all hover:opacity-90"
                    >
                        Deploy Now
                    </NavLink>
                </div>
            </div>
        </header>
    );
}
