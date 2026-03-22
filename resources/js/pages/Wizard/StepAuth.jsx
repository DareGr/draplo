import { useState, forwardRef, useImperativeHandle } from 'react';
import Card from '../../components/Card';
import Toggle from '../../components/Toggle';
import Textarea from '../../components/Textarea';

const StepAuth = forwardRef(function StepAuth({ stepData }, ref) {
    const [multiTenant, setMultiTenant] = useState(stepData.multi_tenant || false);
    const [authMethod] = useState(stepData.auth_method || 'sanctum');
    const [guestAccess, setGuestAccess] = useState(stepData.guest_access || false);
    const [guestDescription, setGuestDescription] = useState(stepData.guest_description || '');

    useImperativeHandle(ref, () => ({
        getData: () => ({
            multi_tenant: multiTenant,
            auth_method: authMethod,
            guest_access: guestAccess,
            guest_description: guestDescription,
        }),
    }));

    return (
        <div className="space-y-8">
            <div className="flex items-center gap-3 mb-8">
                <span className="material-symbols-outlined text-3xl text-primary">shield</span>
                <h2 className="text-2xl font-headline font-bold text-white">Auth & Tenancy</h2>
            </div>

            {/* Multi-tenancy */}
            <Card hover={false}>
                <Toggle
                    checked={multiTenant}
                    onChange={setMultiTenant}
                    label="Enable Multi-tenancy"
                    description="Each customer gets their own isolated workspace with separate data"
                />
            </Card>

            {/* Auth Method */}
            <Card hover={false}>
                <div className="flex items-center gap-4">
                    <div className="w-12 h-12 rounded-xl bg-primary/10 flex items-center justify-center">
                        <span className="material-symbols-outlined text-primary">verified_user</span>
                    </div>
                    <div className="flex-1">
                        <h3 className="text-on-surface font-medium text-sm">Laravel Sanctum</h3>
                        <p className="text-on-surface-variant text-xs mt-0.5">
                            Token-based API authentication
                        </p>
                    </div>
                    <span className="font-label text-[11px] uppercase tracking-widest text-outline">
                        Selected
                    </span>
                </div>
                <p className="text-outline text-xs mt-3 font-mono">
                    Additional auth methods coming in future versions
                </p>
            </Card>

            {/* Guest Access */}
            <Card hover={false}>
                <Toggle
                    checked={guestAccess}
                    onChange={setGuestAccess}
                    label="Allow Guest Access"
                    description="Allow unauthenticated users to access parts of your app"
                />
                {guestAccess && (
                    <div className="mt-4">
                        <Textarea
                            label="Describe what guests can do"
                            value={guestDescription}
                            onChange={(e) => setGuestDescription(e.target.value)}
                            placeholder="e.g. Browse products, view public profiles, read documentation..."
                            rows={3}
                        />
                    </div>
                )}
            </Card>
        </div>
    );
});

export default StepAuth;
