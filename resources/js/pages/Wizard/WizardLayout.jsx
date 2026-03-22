import { useState, useRef } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import useProject, { STEPS, STEP_TITLES } from '../../hooks/useProject';
import AppLayout from '../../components/AppLayout';
import Button from '../../components/Button';
import Toast from '../../components/Toast';
import StepDescribe from './StepDescribe';
import StepUsers from './StepUsers';
import StepModels from './StepModels';
import StepAuth from './StepAuth';
import StepIntegrations from './StepIntegrations';
import StepReview from './StepReview';

const STEP_COMPONENTS = [StepDescribe, StepUsers, StepModels, StepAuth, StepIntegrations, StepReview];

export default function WizardLayout() {
    const { projectId } = useParams();
    const navigate = useNavigate();
    const { project, loading, saving, error, saveStep, getStepData } = useProject(projectId);
    const [currentStep, setCurrentStep] = useState(0);
    const [toast, setToast] = useState(null);
    const stepRef = useRef(null);

    if (loading) {
        return (
            <AppLayout activePage="wizard">
                <div className="flex items-center justify-center h-[calc(100vh-4rem)]">
                    <div className="text-primary font-mono">Loading project...</div>
                </div>
            </AppLayout>
        );
    }

    if (!project) {
        return (
            <AppLayout activePage="wizard">
                <div className="flex flex-col items-center justify-center h-[calc(100vh-4rem)]">
                    <span className="material-symbols-outlined text-5xl text-outline mb-4">error_outline</span>
                    <h1 className="text-xl font-bold text-white mb-2">Project not found</h1>
                    <p className="text-on-surface-variant mb-6">This project doesn't exist or you don't have access.</p>
                    <Button variant="secondary" onClick={() => navigate('/projects')}>Back to Projects</Button>
                </div>
            </AppLayout>
        );
    }

    const currentStepName = STEPS[currentStep];
    const stepData = getStepData(currentStepName);
    const isFirstStep = currentStep === 0;
    const isLastStep = currentStep === STEPS.length - 1;

    const handleNext = async () => {
        // Get data from the step component via ref
        // For now, stepRef.current?.getData() will return the local state
        const data = stepRef.current?.getData?.() || stepData;
        try {
            await saveStep(currentStepName, data);
            if (!isLastStep) {
                setCurrentStep(prev => prev + 1);
            } else {
                setToast({ message: 'Wizard complete!', type: 'success' });
            }
        } catch (err) {
            setToast({ message: err.response?.data?.message || 'Failed to save', type: 'error' });
        }
    };

    const handleBack = () => {
        if (!isFirstStep) setCurrentStep(prev => prev - 1);
    };

    const handleSaveDraft = async () => {
        const data = stepRef.current?.getData?.() || stepData;
        try {
            await saveStep(currentStepName, data);
            setToast({ message: 'Draft saved', type: 'success' });
        } catch (err) {
            setToast({ message: 'Failed to save draft', type: 'error' });
        }
    };

    // Navigate to a specific step (used by Review step's "Edit" links)
    const goToStep = (stepIndex) => setCurrentStep(stepIndex);

    const wizardProgress = {
        step: currentStep + 1,
        totalSteps: STEPS.length,
        templateName: project.template_slug ? project.template_slug.replace(/-/g, ' ').replace(/\b\w/g, c => c.toUpperCase()) : 'Custom Project',
    };

    // Render the appropriate step component
    const renderStep = () => {
        const StepComponent = STEP_COMPONENTS[currentStep];
        if (currentStep === 5) {
            return <StepComponent ref={stepRef} stepData={stepData} project={project} onGoToStep={goToStep} />;
        }
        return <StepComponent ref={stepRef} stepData={stepData} />;
    };

    return (
        <AppLayout activePage="wizard" wizardProgress={wizardProgress}>
            {/* Header */}
            <div className="px-12 pt-10 pb-6">
                <div className="max-w-4xl mx-auto">
                    <div className="text-[11px] font-mono text-on-surface-variant uppercase tracking-[0.2em] mb-3">
                        Template: {wizardProgress.templateName} → <span className="text-primary">Step {currentStep + 1} of {STEPS.length}</span>
                    </div>
                </div>
            </div>

            {/* Step content */}
            <div className="px-12 pb-32">
                <div className="max-w-4xl mx-auto">
                    {renderStep()}
                </div>
            </div>

            {/* Bottom navigation bar */}
            <div className="fixed bottom-0 left-64 right-0 h-20 bg-background/80 backdrop-blur-md border-t border-outline-variant/5 px-12 flex items-center justify-between z-50">
                <button
                    onClick={handleBack}
                    disabled={isFirstStep}
                    className={`flex items-center gap-2 font-bold uppercase text-[11px] tracking-widest transition-colors ${isFirstStep ? 'text-outline cursor-not-allowed' : 'text-on-surface-variant hover:text-on-surface'}`}
                >
                    <span className="material-symbols-outlined">chevron_left</span>
                    {!isFirstStep && `Back: ${STEP_TITLES[currentStep - 1]}`}
                </button>

                <div className="flex gap-4">
                    <Button variant="secondary" onClick={handleSaveDraft} loading={saving} disabled={saving}>
                        Save Draft
                    </Button>
                    <Button variant="primary" onClick={handleNext} loading={saving} disabled={saving}>
                        {isLastStep ? 'Complete Wizard' : `Next: ${STEP_TITLES[currentStep + 1]}`}
                        {!isLastStep && <span className="material-symbols-outlined ml-1">trending_flat</span>}
                    </Button>
                </div>
            </div>

            {/* Toast */}
            {toast && <Toast message={toast.message} type={toast.type} onDismiss={() => setToast(null)} />}
        </AppLayout>
    );
}
