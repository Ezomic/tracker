/** Format whole minutes as "1h 30m" (or "45m", "2h", "0m"). */
export function formatDuration(minutes: number): string {
    if (minutes <= 0) {
        return '0m';
    }

    const hours = Math.floor(minutes / 60);
    const remainder = minutes % 60;

    if (hours === 0) {
        return `${remainder}m`;
    }

    if (remainder === 0) {
        return `${hours}h`;
    }

    return `${hours}h ${remainder}m`;
}
