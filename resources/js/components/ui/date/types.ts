/**
 * Date Input Components Type Definitions
 */

// Domain型: 期限値の表現
export type DeadlineValue =
  | { mode: 'none' }
  | { mode: 'absolute'; date: string; time?: string }
  | { mode: 'relative'; days: number }

// BaseDateInput Props
export interface BaseDateInputProps {
  type: 'date' | 'time' | 'datetime-local'
  modelValue: string
  disabled?: boolean
  placeholder?: string
  class?: string
}

// DateTimeInput Props  
export interface DateTimeInputProps {
  modelValue: Date | string | null
  showTime?: boolean
  format?: string
  locale?: string
  disabled?: boolean
  placeholder?: string
}

// DeadlineInput Props
export interface DeadlineInputProps {
  modelValue: DeadlineValue
  required?: boolean
  allowNoDeadline?: boolean
  relativeOptions?: string[]
  minDate?: Date
}