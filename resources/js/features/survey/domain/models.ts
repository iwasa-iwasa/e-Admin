export type QuestionType =
    | 'single'
    | 'multiple'
    | 'text'
    | 'textarea'
    | 'rating'
    | 'scale'
    | 'dropdown'
    | 'date'

export interface Question {
    id: string | number
    uuid?: string
    type: QuestionType
    question: string
    options: (string | any)[] // string for simple strings, object for Answer view with IDs
    required: boolean
    scaleMin?: number
    scaleMax?: number
    scaleMinLabel?: string
    scaleMaxLabel?: string
}

export interface Survey {
    id?: string | number
    uuid?: string
    title: string
    description: string
    deadline: string
    is_active?: boolean
    version?: number
    questions: Question[]
    respondents: number[] // user ids
}
