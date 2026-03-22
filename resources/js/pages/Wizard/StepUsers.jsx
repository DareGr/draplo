import { useState, forwardRef, useImperativeHandle } from 'react';
import Input from '../../components/Input';

const APP_TYPES = [
    { value: 'b2b_saas', label: 'B2B SaaS' },
    { value: 'b2c_app', label: 'B2C Application' },
    { value: 'marketplace', label: 'Marketplace' },
    { value: 'internal', label: 'Internal Tool' },
    { value: 'api_service', label: 'API Service' },
];

const StepUsers = forwardRef(function StepUsers({ stepData }, ref) {
    const [appType, setAppType] = useState(stepData.app_type || '');
    const [roles, setRoles] = useState(
        stepData.roles?.length
            ? stepData.roles
            : [{ name: 'Admin', description: 'Full system access', removable: false, renameable: false }]
    );

    useImperativeHandle(ref, () => ({
        getData: () => ({ app_type: appType, roles }),
    }));

    const updateRole = (index, field, value) => {
        setRoles((prev) => prev.map((r, i) => (i === index ? { ...r, [field]: value } : r)));
    };

    const removeRole = (index) => {
        setRoles((prev) => prev.filter((_, i) => i !== index));
    };

    const addRole = () => {
        setRoles((prev) => [...prev, { name: '', description: '', removable: true, renameable: true }]);
    };

    return (
        <div className="space-y-8">
            <div className="flex items-center gap-3 mb-8">
                <span className="material-symbols-outlined text-3xl text-primary">group</span>
                <h2 className="text-2xl font-headline font-bold text-white">Who Uses It?</h2>
            </div>

            {/* App Type */}
            <div>
                <label className="block font-label text-[11px] uppercase tracking-widest text-on-surface-variant mb-3">
                    App Type
                </label>
                <div className="flex flex-wrap gap-2">
                    {APP_TYPES.map((type) => (
                        <button
                            key={type.value}
                            type="button"
                            onClick={() => setAppType(type.value)}
                            className={`px-4 py-2 rounded-full text-sm font-medium transition-all ${
                                appType === type.value
                                    ? 'bg-primary-container text-on-primary-container'
                                    : 'bg-surface-container-highest text-on-surface-variant border border-outline-variant/10 hover:bg-surface-container-high'
                            }`}
                        >
                            {type.label}
                        </button>
                    ))}
                </div>
            </div>

            {/* Roles */}
            <div>
                <label className="block font-label text-[11px] uppercase tracking-widest text-on-surface-variant mb-3">
                    Roles
                </label>
                <div className="space-y-3">
                    {roles.map((role, index) => (
                        <div
                            key={index}
                            className="bg-surface-container rounded-xl p-4 border border-outline-variant/5 flex items-start gap-4"
                        >
                            <div className="flex-1 grid grid-cols-1 md:grid-cols-2 gap-3">
                                <div className="relative">
                                    <Input
                                        label="Role Name"
                                        value={role.name}
                                        onChange={(e) => updateRole(index, 'name', e.target.value)}
                                        placeholder="e.g. Manager"
                                        disabled={role.renameable === false}
                                    />
                                    {role.renameable === false && (
                                        <span className="material-symbols-outlined absolute top-0 right-0 text-outline text-sm">
                                            lock
                                        </span>
                                    )}
                                </div>
                                <Input
                                    label="Description"
                                    value={role.description}
                                    onChange={(e) => updateRole(index, 'description', e.target.value)}
                                    placeholder="What can this role do?"
                                />
                            </div>
                            {role.removable !== false ? (
                                <button
                                    type="button"
                                    onClick={() => removeRole(index)}
                                    className="mt-6 text-outline hover:text-error transition-colors"
                                >
                                    <span className="material-symbols-outlined">delete</span>
                                </button>
                            ) : (
                                <span className="mt-6 material-symbols-outlined text-outline/40 text-sm">lock</span>
                            )}
                        </div>
                    ))}

                    {/* Add Role */}
                    <button
                        type="button"
                        onClick={addRole}
                        className="w-full border-2 border-dashed border-outline-variant/15 rounded-xl p-4 text-on-surface-variant hover:border-primary/30 hover:text-primary transition-colors flex items-center justify-center gap-2"
                    >
                        <span className="material-symbols-outlined">add</span>
                        <span className="text-sm font-medium">Add Role</span>
                    </button>
                </div>
            </div>
        </div>
    );
});

export default StepUsers;
