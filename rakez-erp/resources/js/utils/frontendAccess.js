function toObject(value) {
    if (!value || typeof value !== 'object' || Array.isArray(value)) {
        return {};
    }
    return value;
}

/**
 * Build a deterministic sidebar model from backend access profile payload.
 * The frontend should render this model directly without role-name logic.
 */
export function buildSidebarModel(accessProfile) {
    const sections = toObject(accessProfile?.frontend?.sections);
    const output = [];

    for (const [sectionKey, section] of Object.entries(sections)) {
        const visible = section?.visible === true;
        if (!visible) {
            continue;
        }

        const tabs = toObject(section?.tabs);
        const visibleTabs = Object.entries(tabs)
            .filter(([, tab]) => tab?.visible === true)
            .map(([tabKey, tab]) => ({
                key: tabKey,
                label: String(tab?.label ?? tabKey),
                route: String(tab?.route ?? ''),
                visible: true,
            }));

        output.push({
            key: sectionKey,
            label: String(section?.label ?? sectionKey),
            visible: true,
            tabs: visibleTabs,
            actions: toObject(section?.actions),
            hasVisibleTabs: visibleTabs.length > 0,
        });
    }

    return output;
}

export function hasVisibleSections(sidebarModel) {
    return Array.isArray(sidebarModel) && sidebarModel.length > 0;
}

export function buildEmptyState(sidebarModel) {
    return {
        visible: !hasVisibleSections(sidebarModel),
        title: 'No available sections',
        message: 'Your account has no operational sections available right now.',
    };
}
