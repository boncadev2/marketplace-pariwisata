import { MapPin, Navigation, ArrowUpRight } from "lucide-react";

export function PlaceLocation({ place }) {
  const address = place.location?.trim();
  const validCoordinate = value => value !== null && value !== undefined && String(value).trim() !== "" && Number.isFinite(Number(value));
  const hasCoordinates = validCoordinate(place.latitude) && validCoordinate(place.longitude);
  const query = hasCoordinates ? `${place.latitude},${place.longitude}` : address ? `${place.name}, ${address}` : null;
  const available = query && !place.location_is_demo;
  return <section className="place-location" id="lokasi-tempat">
    <span className="heading-kicker"><MapPin size={16} /> Lokasi & akses</span><h2>Lokasi {place.name}</h2>
    <div className="place-location-card"><span className="place-location-icon"><MapPin size={30} /></span><div><strong>{place.name}</strong><p>{address || (hasCoordinates ? `${place.latitude}, ${place.longitude}` : "Alamat lengkap belum ditambahkan pengelola.")}</p>
      {place.location_is_demo && <p className="place-location-demo">Lokasi demonstrasi, bukan alamat properti nyata.</p>}
      {available ? <div className="place-location-actions"><a className="ui-button" href={`https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(query)}`} target="_blank" rel="noopener noreferrer">Buka Google Maps <ArrowUpRight size={16} /></a><a className="ui-button ui-button-outline" href={`https://www.google.com/maps/dir/?api=1&destination=${encodeURIComponent(query)}`} target="_blank" rel="noopener noreferrer"><Navigation size={16} /> Petunjuk arah</a></div> : <p className="place-location-pending">Google Maps tersedia setelah alamat nyata ditambahkan pengelola.</p>}
    </div></div>
  </section>;
}
