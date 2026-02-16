import holiday_jp from '@holiday-jp/holiday_jp'

export const isHoliday = (date: Date | string): boolean => {
  const d = typeof date === 'string' ? new Date(date + 'T00:00:00') : date
  return holiday_jp.isHoliday(d)
}

export const getHolidayName = (date: Date | string): string | null => {
  const d = typeof date === 'string' ? new Date(date + 'T00:00:00') : date
  const holidays = holiday_jp.between(d, d)
  return holidays.length > 0 ? holidays[0].name : null
}

export const isWeekend = (date: Date): boolean => {
  const day = date.getDay()
  return day === 0 || day === 6
}

export const getDayColor = (date: Date): string => {
  const day = date.getDay()
  if (day === 0 || isHoliday(date)) return 'text-red-600 dark:text-red-400'
  if (day === 6) return 'text-blue-600 dark:text-blue-400'
  return ''
}
