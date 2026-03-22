import { useState, useEffect, useCallback } from 'react';
import api from '../api';

const STEPS = ['describe', 'users', 'models', 'auth', 'integrations', 'review'];
const STEP_TITLES = ['Describe Your App', 'Who Uses It?', 'Core Models', 'Auth & Tenancy', 'Integrations', 'Review & Generate'];

export { STEPS, STEP_TITLES };

export default function useProject(projectId) {
    const [project, setProject] = useState(null);
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState(null);

    const load = useCallback(async () => {
        setLoading(true);
        setError(null);
        try {
            const { data } = await api.get(`/wizard/projects/${projectId}`);
            setProject(data);
        } catch (err) {
            setError(err.response?.data?.message || 'Failed to load project');
        } finally {
            setLoading(false);
        }
    }, [projectId]);

    useEffect(() => { load(); }, [load]);

    const saveStep = useCallback(async (step, data) => {
        setSaving(true);
        setError(null);
        try {
            const { data: updated } = await api.put(`/wizard/projects/${projectId}`, { step, data });
            setProject(updated);
            return updated;
        } catch (err) {
            const msg = err.response?.data?.message || 'Failed to save';
            setError(msg);
            throw err;
        } finally {
            setSaving(false);
        }
    }, [projectId]);

    const getStepData = useCallback((step) => {
        return project?.wizard_data?.[`step_${step}`] || {};
    }, [project]);

    return { project, loading, saving, error, saveStep, getStepData, reload: load, STEPS, STEP_TITLES };
}
