import React from 'react';
import Button from '../../components/Button';

export default function WhatNextCard({ repoUrl, repoName, projectSlug, onClose }) {
    const dirName = repoName ? repoName.split('/').pop() : projectSlug || 'project';

    return (
        <div className="fixed inset-0 z-50 bg-background/80 backdrop-blur-sm flex items-center justify-center p-4">
            <div className="bg-surface-container rounded-2xl border border-outline-variant/10 shadow-2xl max-w-lg w-full p-8">
                {/* Success icon */}
                <div className="flex items-center gap-3 mb-6">
                    <span className="material-symbols-outlined text-3xl text-secondary">check_circle</span>
                    <h2 className="text-2xl font-extrabold text-white">Your scaffold is ready!</h2>
                </div>

                {/* Repo link */}
                <a
                    href={repoUrl}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="text-primary font-mono text-sm hover:underline block mb-6"
                >
                    {repoUrl}
                </a>

                {/* Terminal instructions */}
                <div className="bg-surface-container-lowest rounded-xl p-4 font-mono text-xs text-on-surface-variant leading-relaxed mb-6">
                    <div className="text-outline mb-2"># Clone and start building</div>
                    <div>git clone {repoUrl}</div>
                    <div>cd {dirName}</div>
                    <div>docker-compose up -d</div>
                    <div>cp .env.example .env</div>
                    <div>php artisan migrate --seed</div>
                    <div className="text-primary mt-2"># Open Claude Code and start building!</div>
                </div>

                {/* Buttons */}
                <div className="flex gap-3">
                    <a href={repoUrl} target="_blank" rel="noopener noreferrer" className="flex-1">
                        <Button variant="primary" className="w-full">
                            <span className="inline-flex items-center gap-1.5">
                                <span className="material-symbols-outlined text-sm">open_in_new</span>
                                Open on GitHub
                            </span>
                        </Button>
                    </a>
                    <Button variant="secondary" onClick={onClose}>
                        Close
                    </Button>
                </div>
            </div>
        </div>
    );
}
