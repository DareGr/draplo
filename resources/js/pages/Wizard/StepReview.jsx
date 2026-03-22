import { useState, forwardRef, useImperativeHandle } from 'react';
import Card from '../../components/Card';
import Button from '../../components/Button';

const SECTIONS = [
    { key: 'describe', title: 'App Description', icon: 'edit_note', stepIndex: 0 },
    { key: 'users', title: 'Users & Roles', icon: 'group', stepIndex: 1 },
    { key: 'models', title: 'Core Models', icon: 'schema', stepIndex: 2 },
    { key: 'auth', title: 'Auth & Tenancy', icon: 'shield', stepIndex: 3 },
    { key: 'integrations', title: 'Integrations', icon: 'extension', stepIndex: 4 },
];

function SectionHeader({ title, icon, expanded, onToggle, onEdit }) {
    return (
        <div className="flex items-center justify-between cursor-pointer" onClick={onToggle}>
            <div className="flex items-center gap-3">
                <span className="material-symbols-outlined text-primary">{icon}</span>
                <h3 className="text-on-surface font-medium">{title}</h3>
                <span
                    className={`material-symbols-outlined text-outline text-sm transition-transform ${
                        expanded ? 'rotate-180' : ''
                    }`}
                >
                    expand_more
                </span>
            </div>
            <button
                type="button"
                onClick={(e) => {
                    e.stopPropagation();
                    onEdit();
                }}
                className="flex items-center gap-1 text-primary text-sm hover:text-primary-container transition-colors"
            >
                <span className="material-symbols-outlined text-sm">edit</span>
                Edit
            </button>
        </div>
    );
}

function DescribeSection({ data }) {
    return (
        <div className="mt-4 space-y-2">
            <div>
                <span className="font-label text-[11px] uppercase tracking-widest text-outline">Name</span>
                <p className="text-on-surface font-mono text-sm mt-0.5">{data.name || 'Not set'}</p>
            </div>
            <div>
                <span className="font-label text-[11px] uppercase tracking-widest text-outline">Description</span>
                <p className="text-on-surface-variant text-sm mt-0.5">{data.description || 'Not set'}</p>
            </div>
            <div>
                <span className="font-label text-[11px] uppercase tracking-widest text-outline">Problem</span>
                <p className="text-on-surface-variant text-sm mt-0.5">{data.problem || 'Not set'}</p>
            </div>
        </div>
    );
}

function UsersSection({ data }) {
    const typeLabels = {
        b2b_saas: 'B2B SaaS',
        b2c_app: 'B2C Application',
        marketplace: 'Marketplace',
        internal: 'Internal Tool',
        api_service: 'API Service',
    };
    return (
        <div className="mt-4 space-y-2">
            <div>
                <span className="font-label text-[11px] uppercase tracking-widest text-outline">App Type</span>
                <p className="text-on-surface font-mono text-sm mt-0.5">{typeLabels[data.app_type] || 'Not set'}</p>
            </div>
            <div>
                <span className="font-label text-[11px] uppercase tracking-widest text-outline">Roles</span>
                <div className="flex flex-wrap gap-2 mt-1">
                    {(data.roles || []).map((role, i) => (
                        <span
                            key={i}
                            className="inline-flex items-center px-3 py-1 bg-surface-container-highest rounded-lg font-mono text-xs text-on-surface-variant"
                        >
                            {role.name || 'Unnamed'}
                        </span>
                    ))}
                    {(!data.roles || data.roles.length === 0) && (
                        <span className="text-outline text-xs font-mono">No roles defined</span>
                    )}
                </div>
            </div>
        </div>
    );
}

function ModelsSection({ data }) {
    return (
        <div className="mt-4 space-y-2">
            <div className="flex flex-wrap gap-2">
                {(data.models || []).map((model, i) => (
                    <span
                        key={i}
                        className="inline-flex items-center gap-2 px-3 py-1.5 bg-surface-container-highest rounded-lg font-mono text-xs text-white"
                    >
                        {model.name || 'Unnamed'}
                        <span className="text-outline">
                            {(model.fields || []).length} field{(model.fields || []).length !== 1 ? 's' : ''}
                        </span>
                    </span>
                ))}
                {(!data.models || data.models.length === 0) && (
                    <span className="text-outline text-xs font-mono">No models defined</span>
                )}
            </div>
        </div>
    );
}

function AuthSection({ data }) {
    return (
        <div className="mt-4 space-y-2">
            <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <span className="font-label text-[11px] uppercase tracking-widest text-outline">Multi-tenant</span>
                    <p className="text-on-surface font-mono text-sm mt-0.5">{data.multi_tenant ? 'Yes' : 'No'}</p>
                </div>
                <div>
                    <span className="font-label text-[11px] uppercase tracking-widest text-outline">Auth Method</span>
                    <p className="text-on-surface font-mono text-sm mt-0.5">Sanctum</p>
                </div>
                <div>
                    <span className="font-label text-[11px] uppercase tracking-widest text-outline">Guest Access</span>
                    <p className="text-on-surface font-mono text-sm mt-0.5">{data.guest_access ? 'Yes' : 'No'}</p>
                </div>
            </div>
            {data.guest_access && data.guest_description && (
                <div>
                    <span className="font-label text-[11px] uppercase tracking-widest text-outline">
                        Guest Permissions
                    </span>
                    <p className="text-on-surface-variant text-sm mt-0.5">{data.guest_description}</p>
                </div>
            )}
        </div>
    );
}

function IntegrationsSection({ data }) {
    const integrationNames = {
        stripe: 'Stripe Payments',
        sms: 'SMS Notifications',
        email: 'Transactional Email',
        file_storage: 'File Storage',
        ai: 'AI Integration',
        search: 'Full-Text Search',
        websockets: 'Real-time WebSockets',
    };
    return (
        <div className="mt-4 space-y-2">
            <div className="flex flex-wrap gap-2">
                {(data.selected || []).map((key, i) => (
                    <span
                        key={i}
                        className="inline-flex items-center px-3 py-1 bg-primary/10 text-primary rounded-lg font-mono text-xs border border-primary/20"
                    >
                        {integrationNames[key] || key}
                    </span>
                ))}
                {(!data.selected || data.selected.length === 0) && (
                    <span className="text-outline text-xs font-mono">No integrations selected</span>
                )}
            </div>
            {data.notes && (
                <div>
                    <span className="font-label text-[11px] uppercase tracking-widest text-outline">Notes</span>
                    <p className="text-on-surface-variant text-sm mt-0.5">{data.notes}</p>
                </div>
            )}
        </div>
    );
}

const SECTION_RENDERERS = {
    describe: DescribeSection,
    users: UsersSection,
    models: ModelsSection,
    auth: AuthSection,
    integrations: IntegrationsSection,
};

const StepReview = forwardRef(function StepReview({ stepData, project, onGoToStep }, ref) {
    const [expanded, setExpanded] = useState(
        SECTIONS.reduce((acc, s) => ({ ...acc, [s.key]: true }), {})
    );

    useImperativeHandle(ref, () => ({
        getData: () => ({}),
    }));

    const toggleSection = (key) => {
        setExpanded((prev) => ({ ...prev, [key]: !prev[key] }));
    };

    const getStepData = (step) => {
        return project?.wizard_data?.[`step_${step}`] || {};
    };

    return (
        <div className="space-y-6">
            <div className="flex items-center gap-3 mb-8">
                <span className="material-symbols-outlined text-3xl text-primary">checklist</span>
                <h2 className="text-2xl font-headline font-bold text-white">Review & Generate</h2>
            </div>

            {SECTIONS.map((section) => {
                const Renderer = SECTION_RENDERERS[section.key];
                const data = getStepData(section.key);
                return (
                    <Card key={section.key} hover={false}>
                        <SectionHeader
                            title={section.title}
                            icon={section.icon}
                            expanded={expanded[section.key]}
                            onToggle={() => toggleSection(section.key)}
                            onEdit={() => onGoToStep(section.stepIndex)}
                        />
                        {expanded[section.key] && <Renderer data={data} />}
                    </Card>
                );
            })}

            {/* Generate button */}
            <div className="mt-10 text-center">
                <Button variant="primary" className="opacity-50 cursor-not-allowed px-12 py-3" disabled>
                    <span className="material-symbols-outlined mr-2">rocket_launch</span>
                    Generate Scaffold
                </Button>
                <p className="text-on-surface-variant text-xs mt-3 font-mono">
                    AI generation coming in Phase 2
                </p>
            </div>
        </div>
    );
});

export default StepReview;
