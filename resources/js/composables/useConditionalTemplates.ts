/**
 * Frontend-only conditional template parser for Vue
 * Safely processes conditional logic without server-side evaluation
 */

interface ConditionalBlock {
    type: 'if' | 'elseif' | 'else';
    condition?: string;
    content: string;
}

interface ParsedCondition {
    variable: string;
    operator?: string;
    value?: string;
    isBoolean: boolean;
}

export function useConditionalTemplates() {
    /**
     * Parse a condition string into its components
     */
    const parseCondition = (condition: string): ParsedCondition => {
        condition = condition.trim();

        // Check for comparison operators
        // Updated regex to support dots in variable names like event.bits, event.user_name
        const comparisonMatch = condition.match(/^([a-zA-Z0-9_.]+)\s*(>=|<=|>|<|!=|=)\s*(.+)$/);
        if (comparisonMatch) {
            return {
                variable: comparisonMatch[1],
                operator: comparisonMatch[2] === '=' ? '==' : comparisonMatch[2], // Convert = to ==
                value: comparisonMatch[3].trim().replace(/^["']|["']$/g, ''), // Remove quotes if present
                isBoolean: false
            };
        }

        // Otherwise treat as boolean
        return {
            variable: condition,
            isBoolean: true
        };
    };

    /**
     * Evaluate a condition against provided data
     */
    const evaluateCondition = (condition: ParsedCondition, data: Record<string, any>): boolean => {
        const variableValue = data[condition.variable];

        // Boolean evaluation
        if (condition.isBoolean) {
            // Check for 'false', '0', null, undefined, empty string
            return !!variableValue && variableValue !== 'false' && variableValue !== '0';
        }

        // Comparison evaluation
        if (!condition.operator || condition.value === undefined) {
            return false;
        }

        // Try to parse as numbers if both sides look numeric
        const isNumericComparison = !isNaN(Number(variableValue)) && !isNaN(Number(condition.value));

        if (isNumericComparison) {
            const numValue = Number(variableValue);
            const numCompare = Number(condition.value);

            switch (condition.operator) {
                case '>': return numValue > numCompare;
                case '<': return numValue < numCompare;
                case '>=': return numValue >= numCompare;
                case '<=': return numValue <= numCompare;
                case '!=': return numValue !== numCompare;
                case '==': return numValue === numCompare;
                default: return false;
            }
        } else {
            // String comparison
            const strValue = String(variableValue || '');
            const strCompare = String(condition.value);

            switch (condition.operator) {
                case '==': return strValue === strCompare;
                case '!=': return strValue !== strCompare;
                // For string comparison, > and < use lexicographic ordering
                case '>': return strValue > strCompare;
                case '<': return strValue < strCompare;
                case '>=': return strValue >= strCompare;
                case '<=': return strValue <= strCompare;
                default: return false;
            }
        }
    };

    /**
     * Process a template with conditional logic
     */
    const processConditionalBlocks = (template: string, data: Record<string, any>, depth: number = 0): string => {
        // Prevent infinite recursion
        if (depth > 10) {
            console.warn('Maximum conditional nesting depth reached');
            return template;
        }

        // Regex to match conditional blocks
        const conditionalRegex = /\[\[\[if:([^\]]+)]]]([\s\S]*?)\[\[\[endif]]]/;

        let result = template;
        let match;

        while ((match = conditionalRegex.exec(result)) !== null) {
            const fullMatch = match[0];
            const condition = match[1];
            const innerContent = match[2];

            // Parse the inner content for else/elseif blocks
            const blocks: ConditionalBlock[] = [];
            const elseRegex = /\[\[\[else(?:if:([^\]]+))?]]]/g;
            let lastElseIndex = 0;
            let elseMatch;
            let currentCondition = condition;

            while ((elseMatch = elseRegex.exec(innerContent)) !== null) {
                // Add the content before this else/elseif
                blocks.push({
                    type: blocks.length === 0 ? 'if' : 'elseif',
                    condition: currentCondition,
                    content: innerContent.substring(lastElseIndex, elseMatch.index)
                });

                // Set up for the next block
                lastElseIndex = elseMatch.index + elseMatch[0].length;
                currentCondition = elseMatch[1]; // Will be undefined for 'else'
            }

            // Add the final block
            if (lastElseIndex === 0) {
                // No else/elseif found, just a simple if
                blocks.push({
                    type: 'if',
                    condition: condition,
                    content: innerContent
                });
            } else {
                // Add the last else/elseif block
                blocks.push({
                    type: currentCondition === undefined ? 'else' : 'elseif',
                    condition: currentCondition,
                    content: innerContent.substring(lastElseIndex)
                });
            }

            // Evaluate conditions and select content
            let selectedContent = '';
            for (const block of blocks) {
                if (block.type === 'else') {
                    // Else block always matches if we get here
                    selectedContent = block.content;
                    break;
                } else if (block.condition) {
                    const parsedCondition = parseCondition(block.condition);
                    if (evaluateCondition(parsedCondition, data)) {
                        selectedContent = block.content;
                        break;
                    }
                }
            }

            // Process nested conditionals in the selected content
            if (selectedContent.includes('[[[if:')) {
                selectedContent = processConditionalBlocks(selectedContent, data, depth + 1);
            }

            // Replace the entire conditional block with the selected content
            result = result.substring(0, match.index) + selectedContent + result.substring(match.index + fullMatch.length);
        }

        return result;
    };

    /**
     * Process a template with conditional logic
     */
    const processTemplate = (template: string, data: Record<string, any>): string => {
        return processConditionalBlocks(template, data);
    };

    return {
        processTemplate,
        parseCondition,
        evaluateCondition
    };
}
