"use client";

import { useEffect, useState } from "react";
import { Heart, Loader2 } from "lucide-react";
import { apiRequest } from "../lib/api";
import { useRouter } from "next/navigation";

export function WishlistButton({ destinationSlug, destinationName }) {
  const [isWishlisted, setIsWishlisted] = useState(false);
  const [wishlistId, setWishlistId] = useState(null);
  const [loading, setLoading] = useState(false);
  const [initialCheck, setInitialCheck] = useState(true);
  const router = useRouter();

  useEffect(() => {
    let active = true;
    async function checkWishlist() {
      if (!destinationSlug) return;
      try {
        const res = await apiRequest("/account/wishlist");
        if (active && res?.data) {
          const match = res.data.find(
            (item) => item.destination?.slug === destinationSlug
          );
          if (match) {
            setIsWishlisted(true);
            setWishlistId(match.id);
          }
        }
      } catch {
        // user not logged in or network error, ignore gracefully
      } finally {
        if (active) setInitialCheck(false);
      }
    }
    checkWishlist();
    return () => {
      active = false;
    };
  }, [destinationSlug]);

  async function toggle() {
    if (loading) return;
    setLoading(true);

    try {
      if (isWishlisted && wishlistId) {
        await apiRequest(`/account/wishlist/${wishlistId}`, { method: "DELETE" });
        setIsWishlisted(false);
        setWishlistId(null);
      } else {
        const res = await apiRequest("/account/wishlist", {
          method: "POST",
          body: JSON.stringify({ destination_slug: destinationSlug }),
        });
        setIsWishlisted(true);
        if (res?.data?.id) {
          setWishlistId(res.data.id);
        }
      }
    } catch (err) {
      if (err.status === 401) {
        if (window.confirm("Silakan masuk terlebih dahulu untuk menyimpan destinasi ke daftar favorit. Buka halaman masuk?")) {
          router.push("/login");
        }
      } else {
        alert("Gagal memperbarui favorit. Silakan coba lagi.");
      }
    } finally {
      setLoading(false);
    }
  }

  return (
    <button
      type="button"
      onClick={toggle}
      disabled={loading || initialCheck}
      className={`ui-button ${isWishlisted ? "ui-button-accent" : "ui-button-outline"}`}
      style={{
        display: "inline-flex",
        alignItems: "center",
        gap: "0.5rem",
        cursor: loading ? "wait" : "pointer",
        color: isWishlisted ? "#e11d48" : "inherit",
        borderColor: isWishlisted ? "#fecdd3" : undefined,
        background: isWishlisted ? "#fff1f2" : undefined,
      }}
      title={isWishlisted ? "Hapus dari favorit" : "Simpan ke favorit"}
      aria-label={isWishlisted ? `Hapus ${destinationName} dari favorit` : `Simpan ${destinationName} ke favorit`}
    >
      {loading ? (
        <Loader2 size={16} className="animate-spin" />
      ) : (
        <Heart
          size={16}
          fill={isWishlisted ? "#e11d48" : "none"}
          color={isWishlisted ? "#e11d48" : "currentColor"}
        />
      )}
      <span>{isWishlisted ? "Disimpan" : "Favorit"}</span>
    </button>
  );
}
