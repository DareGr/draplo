import { useState, forwardRef, useImperativeHandle } from 'react';
import Input from '../../components/Input';
import Textarea from '../../components/Textarea';

const StepDescribe = forwardRef(function StepDescribe({ stepData }, ref) {
    const [name, setName] = useState(stepData.name || '');
    const [description, setDescription] = useState(stepData.description || '');
    const [problem, setProblem] = useState(stepData.problem || '');

    useImperativeHandle(ref, () => ({
        getData: () => ({ name, description, problem }),
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
        </div>
    );
});

export default StepDescribe;
