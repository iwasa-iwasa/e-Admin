import { Question } from '../../domain/models';

export type InputMode = 'edit' | 'read' | 'preview';

export interface BaseInputProps {
    modelValue?: any;
    question: Question;
    mode?: InputMode;
    error?: string;
}
