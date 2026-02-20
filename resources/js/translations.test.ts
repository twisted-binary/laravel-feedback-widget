import { describe, expect, it } from 'vitest';
import { type FeedbackTranslations, defaultTranslations } from './translations';

describe('translations', () => {
    it('has all keys defined and non-empty', () => {
        for (const [key, value] of Object.entries(defaultTranslations)) {
            expect(value, `${key} should be a non-empty string`).toBeTruthy();
            expect(typeof value, `${key} should be a string`).toBe('string');
        }
    });

    it('partial merge preserves unspecified keys', () => {
        const partial: Partial<FeedbackTranslations> = {
            header: 'Custom Header',
        };

        const merged = { ...defaultTranslations, ...partial };

        expect(merged.header).toBe('Custom Header');
        expect(merged.bugLabel).toBe(defaultTranslations.bugLabel);
        expect(merged.networkError).toBe(defaultTranslations.networkError);
        expect(merged.rateLimitError).toBe(defaultTranslations.rateLimitError);
        expect(merged.maxMessagesError).toBe(defaultTranslations.maxMessagesError);
    });
});
