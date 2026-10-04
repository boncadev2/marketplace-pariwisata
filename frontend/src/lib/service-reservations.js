export function serviceReservationLabel(booking) {
  if (booking.status === "cancelled") return "Dibatalkan";
  if (booking.status !== "reserved_sandbox") return "Perlu pemeriksaan pengelola";
  if (booking.completed_at) return "Layanan selesai (simulasi)";
  if (booking.checked_in_at) return "Sudah check-in (simulasi)";
  if (booking.confirmed_at) return "Dikonfirmasi pengelola (simulasi)";
  return "Menunggu konfirmasi (simulasi)";
}
