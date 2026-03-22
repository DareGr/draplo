import { useState, forwardRef, useImperativeHandle } from 'react';
import Card from '../../components/Card';
import Toggle from '../../components/Toggle';
import Textarea from '../../components/Textarea';

const INTEGRATIONS = [
    { key: 'stripe', icon: 'payments', name: 'Stripe Payments', description: 'Cashier config, webhook handler' },
    { key: 'sms', icon: 'sms', name: 'SMS Notifications', description: 'Notification channel (Twilio/Infobip)' },
    { key: 'email', icon: 'email', name: 'Transactional Email', description: 'Mail config, email templates' },
    { key: 'file_storage', icon: 'cloud_upload', name: 'File Storage', description: 'S3/MinIO filesystem config' },
    { key: 'ai', icon: 'smart_toy', name: 'AI Integration', description: 'Anthropic/OpenAI service setup' },
    { key: 'search', icon: 'search', name: 'Full-Text Search', description: 'Scout + Meilisearch config' },
    { key: 'websockets', icon: 'sync_alt', name: 'Real-time WebSockets', description: 'Laravel Reverb config' },
];

const StepIntegrations = forwardRef(function StepIntegrations({ stepData }, ref) {
    const [selected, setSelected] = useState(stepData.selected || []);
    const [notes, setNotes] = useState(stepData.notes || '');

    useImperativeHandle(ref, () => ({
        getData: () => ({ selected, notes }),
    }));

    const toggleIntegration = (key) => {
        setSelected((prev) =>
            prev.includes(key) ? prev.filter((k) => k !== key) : [...prev, key]
        );
    };

    return (
        <div className="space-y-8">
            <div className="flex items-center gap-3 mb-8">
                <span className="material-symbols-outlined text-3xl text-primary">extension</span>
                <h2 className="text-2xl font-headline font-bold text-white">Integrations</h2>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                {INTEGRATIONS.map((integration) => (
                    <Card key={integration.key} hover={false}>
                        <div className="flex items-center gap-4">
                            <div className="w-10 h-10 rounded-lg bg-surface-container-highest flex items-center justify-center shrink-0">
                                <span className="material-symbols-outlined text-on-surface-variant">
                                    {integration.icon}
                                </span>
                            </div>
                            <div className="flex-1 min-w-0">
                                <h3 className="text-on-surface font-medium text-sm">{integration.name}</h3>
                                <p className="text-on-surface-variant text-xs mt-0.5">{integration.description}</p>
                            </div>
                            <Toggle
                                checked={selected.includes(integration.key)}
                                onChange={() => toggleIntegration(integration.key)}
                            />
                        </div>
                    </Card>
                ))}
            </div>

            <Textarea
                label="Additional notes about integrations"
                value={notes}
                onChange={(e) => setNotes(e.target.value)}
                placeholder="Any specific requirements or configurations for the selected integrations..."
                rows={3}
            />
        </div>
    );
});

export default StepIntegrations;
