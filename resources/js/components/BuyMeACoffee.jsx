export default function BuyMeACoffee({ size = 'default' }) {
    const donateUrl = window.__draplo?.donateUrl || 'https://buymeacoffee.com/darko';
    const githubUrl = window.__draplo?.githubRepoUrl || 'https://github.com/DareGr/draplo';

    if (size === 'small') {
        return (
            <a
                href={donateUrl}
                target="_blank"
                rel="noopener noreferrer"
                className="inline-flex items-center gap-1.5 text-xs font-mono text-on-surface-variant hover:text-primary transition-colors"
            >
                <span className="material-symbols-outlined text-sm">coffee</span>
                Buy Me a Coffee
            </a>
        );
    }

    return (
        <div className="flex flex-col items-center gap-4">
            <div className="flex gap-3">
                <a
                    href={donateUrl}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="inline-flex items-center gap-2 px-5 py-2.5 bg-gradient-to-r from-primary to-primary-container text-on-primary rounded-md font-bold text-sm hover:opacity-90 transition-opacity"
                >
                    <span className="material-symbols-outlined text-lg">coffee</span>
                    Buy Me a Coffee
                </a>
                <a
                    href={githubUrl}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="inline-flex items-center gap-2 px-5 py-2.5 border border-outline-variant/15 text-on-surface rounded-md font-medium text-sm hover:bg-surface-container-high transition-colors"
                >
                    <span className="material-symbols-outlined text-lg">star</span>
                    Star on GitHub
                </a>
            </div>
        </div>
    );
}
