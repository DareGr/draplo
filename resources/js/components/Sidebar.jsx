import { NavLink } from 'react-router-dom';

const navItems = [
    { key: 'dashboard', label: 'Dashboard', icon: 'dashboard', to: '/projects' },
    { key: 'library', label: 'Library', icon: 'grid_view', to: '/templates' },
    { key: 'wizard', label: 'Wizard', icon: 'magic_button', to: '/wizard' },
    { key: 'deployments', label: 'Deployments', icon: 'rocket_launch', to: '/deployments' },
    { key: 'settings', label: 'Settings', icon: 'settings', to: '/settings' },
];

export default function Sidebar({ activePage, wizardProgress }) {
    return (
        <aside className="fixed left-0 top-0 w-64 h-screen bg-surface-container-lowest border-r border-primary/10 z-50 flex flex-col">
            {/* Logo */}
            <div className="px-6 py-5">
                <span className="text-lg font-bold text-primary uppercase tracking-tighter font-headline">
                    Draplo
                </span>
            </div>

            {/* Navigation */}
            <nav className="flex-1 mt-2">
                {navItems.map((item) => {
                    const isActive = activePage === item.key;
                    return (
                        <NavLink
                            key={item.key}
                            to={item.to}
                            className={`flex items-center px-6 py-3 text-sm font-medium transition-all ${
                                isActive
                                    ? 'bg-primary/10 text-primary border-r-2 border-inverse-primary'
                                    : 'text-on-surface-variant hover:text-on-surface hover:bg-primary/5'
                            }`}
                        >
                            <span className="material-symbols-outlined text-[20px] mr-3">
                                {item.icon}
                            </span>
                            {item.label}
                        </NavLink>
                    );
                })}
            </nav>

            {/* Bottom section */}
            <div className="px-4 pb-5 space-y-4">
                {/* Wizard progress widget */}
                {wizardProgress && (
                    <div className="bg-surface-container rounded-md p-3">
                        <div className="flex items-center justify-between mb-2">
                            <span className="text-xs font-label text-on-surface-variant">
                                Step {wizardProgress.step} of {wizardProgress.totalSteps}
                            </span>
                        </div>
                        <div className="w-full h-1 bg-surface-container-high rounded-full overflow-hidden">
                            <div
                                className="h-full bg-primary-container rounded-full transition-all"
                                style={{
                                    width: `${(wizardProgress.step / wizardProgress.totalSteps) * 100}%`,
                                }}
                            />
                        </div>
                        {wizardProgress.templateName && (
                            <p className="text-xs text-on-surface-variant mt-2 font-mono truncate">
                                {wizardProgress.templateName}
                            </p>
                        )}
                    </div>
                )}

                {/* New Project button */}
                <NavLink
                    to="/wizard"
                    className="flex items-center justify-center w-full py-2.5 bg-primary-container text-on-primary-container rounded-md font-bold text-sm transition-all hover:opacity-90"
                >
                    <span className="material-symbols-outlined text-[18px] mr-2">add</span>
                    New Project
                </NavLink>

                {/* User info */}
                <div className="flex items-center gap-3 px-2 pt-2">
                    <div className="w-8 h-8 rounded-full bg-surface-container-high flex items-center justify-center">
                        <span className="material-symbols-outlined text-[16px] text-on-surface-variant">
                            person
                        </span>
                    </div>
                    <div className="min-w-0">
                        <p className="text-xs font-label text-primary truncate">Pro Plan</p>
                        <p className="text-[10px] font-mono text-on-surface-variant">Architect Mode</p>
                    </div>
                </div>
            </div>
        </aside>
    );
}
