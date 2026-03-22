import React, { useState, useMemo } from 'react';

function buildTree(files) {
    const root = { name: '', children: {}, files: [] };

    for (const file of files) {
        const parts = file.path.split('/');
        let current = root;

        for (let i = 0; i < parts.length - 1; i++) {
            const dir = parts[i];
            if (!current.children[dir]) {
                current.children[dir] = { name: dir, children: {}, files: [] };
            }
            current = current.children[dir];
        }

        current.files.push({
            name: parts[parts.length - 1],
            path: file.path,
            size: file.content ? new Blob([file.content]).size : 0,
        });
    }

    return root;
}

function getFileIcon(name) {
    if (name.endsWith('.md')) return 'description';
    if (name.endsWith('.php')) return 'code';
    if (name.endsWith('.json')) return 'data_object';
    if (name.endsWith('.js') || name.endsWith('.jsx')) return 'javascript';
    if (name.endsWith('.css') || name.endsWith('.scss')) return 'style';
    if (name.endsWith('.yml') || name.endsWith('.yaml')) return 'settings';
    if (name.endsWith('.env') || name.endsWith('.env.example')) return 'lock';
    return 'insert_drive_file';
}

function formatSize(bytes) {
    if (bytes < 1024) return `${bytes} B`;
    return `${(bytes / 1024).toFixed(1)} KB`;
}

function DirectoryNode({ name, node, depth, activeFile, onSelectFile, expanded, toggleExpanded, fullPath }) {
    const isExpanded = expanded[fullPath] !== false;

    const sortedDirs = Object.keys(node.children).sort();
    const sortedFiles = [...node.files].sort((a, b) => a.name.localeCompare(b.name));

    return (
        <div>
            <button
                onClick={() => toggleExpanded(fullPath)}
                className="flex items-center gap-2 px-3 py-1.5 cursor-pointer hover:bg-surface-container-low transition-colors text-on-surface-variant w-full text-left"
                style={{ paddingLeft: `${depth * 16 + 12}px` }}
            >
                <span className="material-symbols-outlined text-[18px] text-outline transition-transform" style={{ transform: isExpanded ? 'rotate(90deg)' : 'rotate(0deg)' }}>
                    chevron_right
                </span>
                <span className="material-symbols-outlined text-[18px] text-primary-container">
                    {isExpanded ? 'folder_open' : 'folder'}
                </span>
                <span className="text-sm truncate">{name}</span>
            </button>

            {isExpanded && (
                <div>
                    {sortedDirs.map((dirName) => (
                        <DirectoryNode
                            key={dirName}
                            name={dirName}
                            node={node.children[dirName]}
                            depth={depth + 1}
                            activeFile={activeFile}
                            onSelectFile={onSelectFile}
                            expanded={expanded}
                            toggleExpanded={toggleExpanded}
                            fullPath={`${fullPath}/${dirName}`}
                        />
                    ))}
                    {sortedFiles.map((file) => (
                        <FileNode
                            key={file.path}
                            file={file}
                            depth={depth + 1}
                            activeFile={activeFile}
                            onSelectFile={onSelectFile}
                        />
                    ))}
                </div>
            )}
        </div>
    );
}

function FileNode({ file, depth, activeFile, onSelectFile }) {
    const isActive = activeFile === file.path;

    return (
        <button
            onClick={() => onSelectFile(file.path)}
            className={`flex items-center gap-2 px-3 py-1.5 cursor-pointer transition-colors w-full text-left ${
                isActive
                    ? 'bg-primary/10 text-primary rounded'
                    : 'hover:bg-surface-container-low text-on-surface-variant'
            }`}
            style={{ paddingLeft: `${depth * 16 + 12 + 26}px` }}
        >
            <span className="material-symbols-outlined text-[18px] text-outline">
                {getFileIcon(file.name)}
            </span>
            <span className="text-sm truncate flex-1">{file.name}</span>
            <span className="font-mono text-[10px] text-outline shrink-0">
                {formatSize(file.size)}
            </span>
        </button>
    );
}

export default function FileTree({ files = [], activeFile, onSelectFile }) {
    const [expanded, setExpanded] = useState({});

    const tree = useMemo(() => buildTree(files), [files]);

    const toggleExpanded = (path) => {
        setExpanded((prev) => ({
            ...prev,
            [path]: prev[path] === false ? true : false,
        }));
    };

    const sortedDirs = Object.keys(tree.children).sort();
    const sortedFiles = [...tree.files].sort((a, b) => a.name.localeCompare(b.name));

    return (
        <div className="h-full overflow-y-auto bg-surface-container-lowest py-4">
            {sortedDirs.map((dirName) => (
                <DirectoryNode
                    key={dirName}
                    name={dirName}
                    node={tree.children[dirName]}
                    depth={0}
                    activeFile={activeFile}
                    onSelectFile={onSelectFile}
                    expanded={expanded}
                    toggleExpanded={toggleExpanded}
                    fullPath={dirName}
                />
            ))}
            {sortedFiles.map((file) => (
                <FileNode
                    key={file.path}
                    file={file}
                    depth={0}
                    activeFile={activeFile}
                    onSelectFile={onSelectFile}
                />
            ))}
        </div>
    );
}
