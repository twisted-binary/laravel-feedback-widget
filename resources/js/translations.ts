export interface FeedbackTranslations {
    // Type labels
    bugLabel: string;
    featureLabel: string;
    feedbackLabel: string;

    // Type nouns (used in descriptions like "Describe your {noun}")
    bugNoun: string;
    featureNoun: string;
    feedbackNoun: string;

    // Header
    header: string;

    // Empty state
    emptyStateTitle: string;
    emptyStateTitleFeedback: string;
    emptyStateDescription: string;
    emptyStateDescriptionFeedback: string;

    // Success
    successMessage: string;
    startOver: string;
    close: string;

    // Placeholders
    inputPlaceholder: string;
    creatingIssuePlaceholder: string;

    // Aria labels
    closeFeedback: string;
    sendFeedback: string;
    screenshotPreview: string;

    // Error messages
    sessionExpired: string;
    validationError: string;
    genericError: string;
    networkError: string;
    issueCreationError: string;
    rateLimitError: string;
    maxMessagesError: string;
}

export const defaultTranslations: FeedbackTranslations = {
    // Type labels
    bugLabel: 'Bug',
    featureLabel: 'Feature',
    feedbackLabel: 'Feedback',

    // Type nouns
    bugNoun: 'issue',
    featureNoun: 'idea',
    feedbackNoun: 'feedback',

    // Header
    header: 'Send Feedback',

    // Empty state
    emptyStateTitle: 'How can we help?',
    emptyStateTitleFeedback: 'How are we doing?',
    emptyStateDescription: "Describe your {noun} and we'll guide you through the details.",
    emptyStateDescriptionFeedback: 'Rate your experience and tell us what you think.',

    // Success
    successMessage: 'Thank you for your feedback!',
    startOver: 'Start over',
    close: 'Close',

    // Placeholders
    inputPlaceholder: 'Type your message...',
    creatingIssuePlaceholder: 'Creating issue...',

    // Aria labels
    closeFeedback: 'Close feedback',
    sendFeedback: 'Send feedback',
    screenshotPreview: 'Screenshot preview',

    // Error messages
    sessionExpired: 'Session expired. Please refresh the page.',
    validationError: 'Validation error. Please check your input.',
    genericError: 'Something went wrong. Please try again.',
    networkError: 'Network error. Please check your connection.',
    issueCreationError: 'Failed to create issue. Please try again.',
    rateLimitError: 'Too many requests. Please wait a moment and try again.',
    maxMessagesError: 'Conversation limit reached. Please start a new conversation.',
};
