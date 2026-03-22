import { useState, forwardRef, useImperativeHandle } from 'react';
import Input from '../../components/Input';

const FIELD_TYPES = ['string', 'text', 'integer', 'decimal', 'boolean', 'timestamp', 'foreignId', 'json'];

function getFieldPrefix(type) {
    switch (type) {
        case 'foreignId':
            return { label: 'FK', className: 'text-primary' };
        case 'timestamp':
            return { label: 'T', className: 'text-indigo-300' };
        case 'integer':
            return { label: '#', className: 'text-primary' };
        case 'decimal':
            return { label: '$', className: 'text-green-400' };
        case 'boolean':
            return { label: '?', className: 'text-yellow-400' };
        default:
            return null;
    }
}

function deriveRelationships(models) {
    const relationships = [];
    models.forEach((model) => {
        (model.fields || []).forEach((field) => {
            if (field.type === 'foreignId' && field.name.endsWith('_id')) {
                const referenced = field.name
                    .replace(/_id$/, '')
                    .split('_')
                    .map((w) => w.charAt(0).toUpperCase() + w.slice(1))
                    .join('');
                relationships.push({ from: model.name, to: referenced });
            }
        });
    });
    return relationships;
}

const StepModels = forwardRef(function StepModels({ stepData }, ref) {
    const [models, setModels] = useState(stepData.models?.length ? stepData.models : []);
    const [addingFieldFor, setAddingFieldFor] = useState(null);
    const [newFieldName, setNewFieldName] = useState('');
    const [newFieldType, setNewFieldType] = useState('string');

    useImperativeHandle(ref, () => ({
        getData: () => ({ models }),
    }));

    const updateModel = (index, field, value) => {
        setModels((prev) => prev.map((m, i) => (i === index ? { ...m, [field]: value } : m)));
    };

    const removeModel = (index) => {
        setModels((prev) => prev.filter((_, i) => i !== index));
    };

    const addModel = () => {
        setModels((prev) => [...prev, { name: '', locked: false, description: '', fields: [] }]);
    };

    const addField = (modelIndex) => {
        if (!newFieldName.trim()) return;
        setModels((prev) =>
            prev.map((m, i) =>
                i === modelIndex
                    ? { ...m, fields: [...(m.fields || []), { name: newFieldName.trim(), type: newFieldType }] }
                    : m
            )
        );
        setNewFieldName('');
        setNewFieldType('string');
        setAddingFieldFor(null);
    };

    const removeField = (modelIndex, fieldIndex) => {
        setModels((prev) =>
            prev.map((m, i) =>
                i === modelIndex ? { ...m, fields: (m.fields || []).filter((_, fi) => fi !== fieldIndex) } : m
            )
        );
    };

    const relationships = deriveRelationships(models);

    return (
        <div className="space-y-6">
            <div className="flex items-center gap-3 mb-8">
                <span className="material-symbols-outlined text-3xl text-primary">schema</span>
                <h2 className="text-2xl font-headline font-bold text-white">Core Models</h2>
            </div>

            <div className="space-y-4">
                {models.map((model, modelIndex) => (
                    <div
                        key={modelIndex}
                        className="bg-surface-container rounded-xl p-5 border border-outline-variant/5"
                    >
                        {/* Model header */}
                        <div className="flex items-center gap-3 mb-4">
                            <span className="material-symbols-outlined text-outline/40 cursor-grab">
                                drag_indicator
                            </span>
                            {model.locked ? (
                                <div className="flex items-center gap-2">
                                    <span className="font-mono font-bold text-lg text-white">{model.name}</span>
                                    <span className="inline-flex items-center gap-1 bg-primary/10 text-primary border border-primary/20 px-2 py-0.5 rounded text-[10px] font-mono uppercase">
                                        <span className="material-symbols-outlined text-xs">lock</span>
                                        Locked
                                    </span>
                                </div>
                            ) : (
                                <input
                                    type="text"
                                    value={model.name}
                                    onChange={(e) => updateModel(modelIndex, 'name', e.target.value)}
                                    placeholder="ModelName"
                                    className="bg-transparent font-mono font-bold text-lg text-white border-none outline-none placeholder:text-outline flex-1"
                                />
                            )}
                            {!model.locked && (
                                <button
                                    type="button"
                                    onClick={() => removeModel(modelIndex)}
                                    className="text-outline hover:text-error transition-colors ml-auto"
                                >
                                    <span className="material-symbols-outlined">delete</span>
                                </button>
                            )}
                        </div>

                        {/* Fields */}
                        <div className="flex flex-wrap gap-2">
                            {(model.fields || []).map((field, fieldIndex) => {
                                const prefix = getFieldPrefix(field.type);
                                const isFk = field.type === 'foreignId';
                                return (
                                    <div
                                        key={fieldIndex}
                                        className={`group inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg font-mono text-xs ${
                                            isFk
                                                ? 'bg-primary/20 border border-primary/30 text-white'
                                                : 'bg-surface-container-highest border border-outline-variant/5 text-white'
                                        }`}
                                    >
                                        {prefix && (
                                            <span className={`font-bold ${prefix.className}`}>{prefix.label}</span>
                                        )}
                                        <span>{field.name}</span>
                                        {!model.locked && (
                                            <button
                                                type="button"
                                                onClick={() => removeField(modelIndex, fieldIndex)}
                                                className="opacity-0 group-hover:opacity-100 text-outline hover:text-error transition-all ml-1"
                                            >
                                                <span className="material-symbols-outlined text-sm">close</span>
                                            </button>
                                        )}
                                    </div>
                                );
                            })}

                            {/* Add field button or inline form */}
                            {addingFieldFor === modelIndex ? (
                                <div className="flex items-center gap-2 w-full mt-2">
                                    <input
                                        type="text"
                                        value={newFieldName}
                                        onChange={(e) => setNewFieldName(e.target.value)}
                                        placeholder="field_name"
                                        className="bg-surface-container-lowest font-mono text-sm text-on-surface px-3 py-1.5 rounded-md border border-outline-variant/15 outline-none flex-1"
                                        onKeyDown={(e) => e.key === 'Enter' && addField(modelIndex)}
                                        autoFocus
                                    />
                                    <select
                                        value={newFieldType}
                                        onChange={(e) => setNewFieldType(e.target.value)}
                                        className="bg-surface-container-lowest font-mono text-sm text-on-surface px-3 py-1.5 rounded-md border border-outline-variant/15 outline-none"
                                    >
                                        {FIELD_TYPES.map((t) => (
                                            <option key={t} value={t}>
                                                {t}
                                            </option>
                                        ))}
                                    </select>
                                    <button
                                        type="button"
                                        onClick={() => addField(modelIndex)}
                                        className="text-primary hover:text-primary-container transition-colors"
                                    >
                                        <span className="material-symbols-outlined">check</span>
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => {
                                            setAddingFieldFor(null);
                                            setNewFieldName('');
                                            setNewFieldType('string');
                                        }}
                                        className="text-outline hover:text-on-surface transition-colors"
                                    >
                                        <span className="material-symbols-outlined">close</span>
                                    </button>
                                </div>
                            ) : (
                                !model.locked && (
                                    <button
                                        type="button"
                                        onClick={() => {
                                            setAddingFieldFor(modelIndex);
                                            setNewFieldName('');
                                            setNewFieldType('string');
                                        }}
                                        className="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg font-mono text-xs text-on-surface-variant border border-dashed border-outline-variant/15 hover:border-primary/30 hover:text-primary transition-colors"
                                    >
                                        <span className="material-symbols-outlined text-sm">add</span>
                                        Add field
                                    </button>
                                )
                            )}
                        </div>
                    </div>
                ))}

                {/* Add Model */}
                <button
                    type="button"
                    onClick={addModel}
                    className="w-full border-2 border-dashed border-outline-variant/15 rounded-xl p-5 text-on-surface-variant hover:border-primary/30 hover:text-primary transition-colors flex items-center justify-center gap-2"
                >
                    <span className="material-symbols-outlined">add</span>
                    <span className="text-sm font-medium">Add New Core Model</span>
                </button>
            </div>

            {/* Floating relationships widget */}
            <div className="hidden xl:block fixed right-8 top-32 w-72 bg-surface-container/80 backdrop-blur-lg border border-outline-variant/10 rounded-xl p-5 z-40">
                <div className="flex items-center gap-2 mb-4">
                    <span className="material-symbols-outlined text-primary">account_tree</span>
                    <span className="font-label text-[11px] uppercase tracking-widest text-on-surface-variant">
                        Relationships
                    </span>
                </div>
                {relationships.length > 0 ? (
                    <div className="space-y-2">
                        {relationships.map((rel, i) => (
                            <div key={i} className="font-mono text-xs text-on-surface-variant">
                                <span className="text-white">{rel.from}</span>
                                <span className="text-outline mx-2">belongs to</span>
                                <span className="text-primary">{rel.to}</span>
                            </div>
                        ))}
                    </div>
                ) : (
                    <p className="text-outline text-xs font-mono">
                        Add foreignId fields ending in _id to see relationships
                    </p>
                )}
            </div>
        </div>
    );
});

export default StepModels;
