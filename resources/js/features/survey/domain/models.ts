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
    title: string
    description: string
    deadline: string
    questions: Question[]
    respondents: number[] // user ids
}
