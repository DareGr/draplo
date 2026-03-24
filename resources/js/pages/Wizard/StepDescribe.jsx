import { useState, forwardRef, useImperativeHandle } from 'react';
import Input from '../../components/Input';
import Textarea from '../../components/Textarea';

const StepDescribe = forwardRef(function StepDescribe({ stepData }, ref) {
    const [name, setName] = useState(stepData.name || '');
    const [description, setDescription] = useState(stepData.description || '');
    const [problem, setProblem] = useState(stepData.problem || '');
    const [laravelVersion, setLaravelVersion] = useState(stepData.laravel_version || '12');

    useImperativeHandle(ref, () => ({
        getData: () => ({ name, description, problem, laravel_version: laravelVersion }),
    }));

    return (
        <div className="space-y-8">
            <div className="flex items-center gap-3 mb-8">
                <span className="material-symbols-outlined text-3xl text-primary">edit_note</span>
                <h2 className="text-2xl font-headline font-bold text-white">Describe Your App</h2>
            </div>

            <Input
                label="What's your app called?"
                value={name}
                onChange={(e) => setName(e.target.value)}
                placeholder="e.g. InvoiceBot, TeamSync, DataPulse"
                required
            />

            <Textarea
                label="Describe your app in a sentence"
                value={description}
                onChange={(e) => setDescription(e.target.value)}
                placeholder="A brief overview of what your application does..."
                rows={3}
            />

            <Textarea
                label="What problem does this solve?"
                value={problem}
                onChange={(e) => setProblem(e.target.value)}
                placeholder="Describe the pain point your users currently face..."
                rows={3}
            />

            {/* Laravel Version */}
            <div className="mt-6">
                <label className="text-sm font-label text-on-surface-variant uppercase tracking-widest mb-2 block">
                    Laravel Version
                </label>
                <div className="flex gap-2">
                    {['10', '11', '12', '13'].map((v) => (
                        <button
                            key={v}
                            type="button"
                            onClick={() => setLaravelVersion(v)}
                            className={`px-4 py-2 rounded-md font-mono text-sm transition-colors ${
                                laravelVersion === v
                                    ? 'bg-primary/15 text-primary border border-primary/30'
                                    : 'bg-surface-container-high text-on-surface-variant border border-outline-variant/10 hover:bg-surface-container-highest'
                            }`}
                        >
                            {v}
                        </button>
                    ))}
                </div>
                <p className="text-xs text-on-surface-variant mt-1.5 font-mono">
                    {laravelVersion === '13' && 'Recommended — latest features'}
                    {laravelVersion === '12' && 'Stable — well tested, wide ecosystem'}
                    {laravelVersion === '11' && 'Simplified structure — no Kernel.php'}
                    {laravelVersion === '10' && 'Legacy — PHP 8.1+ compatible'}
                </p>
            </div>
        </div>
    );
});

export default StepDescribe;
