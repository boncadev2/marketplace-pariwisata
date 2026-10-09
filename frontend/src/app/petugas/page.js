"use client";

import { useEffect, useRef, useState, useCallback } from "react";
import Link from "next/link";
import jsQR from "jsqr";
import {
  Ticket,
  CheckCircle2,
  AlertCircle,
  Clock,
  UserCheck,
  Calendar,
  Users,
  Search,
  Camera,
  RefreshCw,
  ShieldCheck,
  AlertTriangle,
  ArrowRight,
  Loader2,
  SwitchCamera,
  Zap,
  X,
  Upload,
} from "lucide-react";
import { PageHeader } from "../../components/PageHeader";
import { Shell } from "../../components/Shell";
import { apiRequest } from "../../lib/api";

function extractVoucherToken(text) {
  if (!text || typeof text !== "string") return null;
  const trimmed = text.trim();
  if (/^[A-Za-z0-9]{48}$/.test(trimmed)) {
    return trimmed;
  }
  const match = trimmed.match(/(?:token=|\/voucher\/|\/vouchers\/)?([A-Za-z0-9]{48})/);
  if (match && match[1]) {
    return match[1];
  }
  return null;
}

function playBeep() {
  try {
    if (typeof navigator !== "undefined" && typeof navigator.vibrate === "function") {
      navigator.vibrate([100, 50, 100]);
    }
    const ctx = new (window.AudioContext || window.webkitAudioContext)();
    const osc = ctx.createOscillator();
    const gain = ctx.createGain();
    osc.type = "sine";
    osc.frequency.setValueAtTime(880, ctx.currentTime);
    gain.gain.setValueAtTime(0.15, ctx.currentTime);
    gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.15);
    osc.connect(gain);
    gain.connect(ctx.destination);
    osc.start();
    osc.stop(ctx.currentTime + 0.15);
  } catch {
    // AudioContext blocked or unsupported
  }
}

export default function StaffPage() {
  const [profile, setProfile] = useState(null);
  const [loadingProfile, setLoadingProfile] = useState(true);
  const [token, setToken] = useState("");
  const [checking, setChecking] = useState(false);
  const [busy, setBusy] = useState(false);
  const [voucherDetails, setVoucherDetails] = useState(null);
  const [overrideDate, setOverrideDate] = useState(false);
  const [overrideReason, setOverrideReason] = useState("");
  const [successResult, setSuccessResult] = useState(null);
  const [error, setError] = useState("");
  const [message, setMessage] = useState("");

  // Live Camera states
  const [cameraActive, setCameraActive] = useState(false);
  const [cameraFacing, setCameraFacing] = useState("environment");
  const [torchOn, setTorchOn] = useState(false);
  const [hasTorch, setHasTorch] = useState(false);
  const [cameraError, setCameraError] = useState("");

  const videoRef = useRef(null);
  const streamRef = useRef(null);
  const scanAnimFrameRef = useRef(null);

  // Check user profile on mount
  useEffect(() => {
    let active = true;
    apiRequest("/me")
      .then((res) => {
        if (!active) return;
        setProfile(res.data);
      })
      .catch(() => {
        if (!active) return;
        setProfile(null);
      })
      .finally(() => {
        if (active) setLoadingProfile(false);
      });

    return () => {
      active = false;
    };
  }, []);

  const stopCamera = useCallback(() => {
    if (scanAnimFrameRef.current) {
      cancelAnimationFrame(scanAnimFrameRef.current);
      scanAnimFrameRef.current = null;
    }
    if (streamRef.current) {
      streamRef.current.getTracks().forEach((track) => track.stop());
      streamRef.current = null;
    }
    if (videoRef.current) {
      videoRef.current.srcObject = null;
    }
    setCameraActive(false);
    setTorchOn(false);
  }, []);

  const checkVoucher = useCallback(async (tokenToTest) => {
    setError("");
    setMessage("");
    setVoucherDetails(null);
    setSuccessResult(null);
    setChecking(true);

    try {
      const res = await apiRequest("/staff/vouchers/check", {
        method: "POST",
        body: JSON.stringify({ token: tokenToTest }),
      });
      setVoucherDetails(res.data);
      if (!res.data.is_today && res.data.status === "active") {
        setMessage(
          `Perhatian: Tanggal voucher adalah ${res.data.service_date_formatted}, sedangkan hari ini ${res.data.today_date}.`
        );
      }
    } catch (err) {
      setError(
        err.message ||
          "Gagal memeriksa voucher. Pastikan kode benar dan akun Anda memiliki izin petugas."
      );
    } finally {
      setChecking(false);
    }
  }, []);

  const startScanLoop = useCallback(() => {
    let barcodeDetector = null;
    if ("BarcodeDetector" in window) {
      try {
        barcodeDetector = new window.BarcodeDetector({ formats: ["qr_code"] });
      } catch {
        barcodeDetector = null;
      }
    }

    const offscreenCanvas = document.createElement("canvas");
    const ctx = offscreenCanvas.getContext("2d", { willReadFrequently: true });

    async function tick() {
      if (!videoRef.current || !streamRef.current) return;
      const video = videoRef.current;

      if (video.readyState >= video.HAVE_CURRENT_DATA && video.videoWidth > 0) {
        let detectedText = null;

        if (barcodeDetector) {
          try {
            const barcodes = await barcodeDetector.detect(video);
            if (barcodes.length > 0 && barcodes[0].rawValue) {
              detectedText = barcodes[0].rawValue;
            }
          } catch {
            // fallback to jsQR
          }
        }

        if (!detectedText && ctx) {
          offscreenCanvas.width = video.videoWidth;
          offscreenCanvas.height = video.videoHeight;
          ctx.drawImage(video, 0, 0, offscreenCanvas.width, offscreenCanvas.height);
          const imageData = ctx.getImageData(0, 0, offscreenCanvas.width, offscreenCanvas.height);
          const code = jsQR(imageData.data, imageData.width, imageData.height, {
            inversionAttempts: "dontInvert",
          });
          if (code && code.data) {
            detectedText = code.data;
          }
        }

        if (detectedText) {
          const foundToken = extractVoucherToken(detectedText);
          if (foundToken) {
            playBeep();
            stopCamera();
            setToken(foundToken);
            setMessage("Kode QR voucher berhasil dipindai. Memeriksa rincian…");
            await checkVoucher(foundToken);
            return;
          }
        }
      }

      scanAnimFrameRef.current = requestAnimationFrame(tick);
    }

    scanAnimFrameRef.current = requestAnimationFrame(tick);
  }, [checkVoucher, stopCamera]);

  const startCamera = useCallback(async (facing = cameraFacing) => {
    stopCamera();
    setCameraError("");
    setTorchOn(false);
    setHasTorch(false);

    try {
      if (!navigator.mediaDevices?.getUserMedia) {
        throw new Error("Peramban ini tidak mendukung akses kamera langsung.");
      }

      const stream = await navigator.mediaDevices.getUserMedia({
        video: {
          facingMode: facing,
          width: { ideal: 1280 },
          height: { ideal: 720 },
        },
        audio: false,
      });

      streamRef.current = stream;
      setCameraActive(true);

      const track = stream.getVideoTracks()[0];
      if (track && typeof track.getCapabilities === "function") {
        const caps = track.getCapabilities();
        if ("torch" in caps) {
          setHasTorch(true);
        }
      }
    } catch (err) {
      console.error("Camera access error:", err);
      let msg = "Tidak dapat mengakses kamera.";
      if (err.name === "NotAllowedError" || err.name === "PermissionDeniedError") {
        msg = "Izin kamera ditolak. Silakan izinkan akses kamera pada peramban Anda untuk memindai tiket.";
      } else if (err.name === "NotFoundError" || err.name === "DevicesNotFoundError") {
        msg = "Kamera tidak ditemukan pada perangkat Anda.";
      } else if (err.name === "NotReadableError" || err.name === "TrackStartError") {
        msg = "Kamera sedang digunakan oleh aplikasi lain.";
      }
      setCameraError(msg);
      setCameraActive(false);
    }
  }, [cameraFacing, stopCamera]);

  useEffect(() => {
    if (cameraActive && streamRef.current && videoRef.current) {
      videoRef.current.srcObject = streamRef.current;
      videoRef.current.setAttribute("playsinline", "true");
      videoRef.current
        .play()
        .then(() => {
          startScanLoop();
        })
        .catch((e) => console.error("Video play error:", e));
    }
  }, [cameraActive, startScanLoop]);

  useEffect(() => {
    return () => {
      stopCamera();
    };
  }, [stopCamera]);

  async function toggleCameraFacing() {
    const nextFacing = cameraFacing === "environment" ? "user" : "environment";
    setCameraFacing(nextFacing);
    await startCamera(nextFacing);
  }

  async function toggleTorch() {
    if (!streamRef.current) return;
    const track = streamRef.current.getVideoTracks()[0];
    if (!track) return;
    try {
      const nextState = !torchOn;
      await track.applyConstraints({
        advanced: [{ torch: nextState }],
      });
      setTorchOn(nextState);
    } catch (err) {
      console.error("Torch error:", err);
    }
  }

  // Scan QR code from photo
  async function scan(event) {
    const file = event.target.files?.[0];
    if (!file) return;
    setError("");
    setMessage("");
    stopCamera();

    try {
      let rawText = null;

      if ("BarcodeDetector" in window) {
        try {
          const bitmap = await createImageBitmap(file);
          const detector = new window.BarcodeDetector({ formats: ["qr_code"] });
          const results = await detector.detect(bitmap);
          bitmap.close();
          if (results.length > 0 && results[0].rawValue) {
            rawText = results[0].rawValue;
          }
        } catch {
          // fallback
        }
      }

      if (!rawText) {
        rawText = await new Promise((resolve, reject) => {
          const reader = new FileReader();
          reader.onload = (e) => {
            const img = new Image();
            img.onload = () => {
              const canvas = document.createElement("canvas");
              canvas.width = img.width;
              canvas.height = img.height;
              const ctx = canvas.getContext("2d");
              ctx.drawImage(img, 0, 0);
              const imgData = ctx.getImageData(0, 0, canvas.width, canvas.height);
              const code = jsQR(imgData.data, imgData.width, imgData.height);
              resolve(code?.data || null);
            };
            img.onerror = reject;
            img.src = e.target.result;
          };
          reader.onerror = reject;
          reader.readAsDataURL(file);
        });
      }

      const extracted = extractVoucherToken(rawText);
      if (!extracted) {
        throw new Error("Kode QR voucher 48 karakter tidak ditemukan pada gambar.");
      }
      playBeep();
      setToken(extracted);
      setMessage("Kode voucher berhasil terbaca dari gambar. Memeriksa rincian…");
      await checkVoucher(extracted);
    } catch (err) {
      setError(err.message || "Foto tidak dapat dibaca. Silakan masukkan kode voucher secara manual.");
    } finally {
      event.target.value = "";
    }
  }

  // Check voucher details before redeeming
  async function handleCheck(event) {
    if (event) event.preventDefault();
    const cleanToken = token.trim();
    if (!cleanToken) return;
    await checkVoucher(cleanToken);
  }

  // Redeem voucher
  async function redeem(event) {
    event.preventDefault();
    if (busy) return;
    const cleanToken = token.trim();
    if (!cleanToken) return;

    setBusy(true);
    setError("");
    setMessage("");

    const payload = { token: cleanToken };
    if (overrideDate && overrideReason.trim()) {
      payload.override_reason = overrideReason.trim();
    }

    try {
      const result = await apiRequest("/staff/vouchers/redeem", {
        method: "POST",
        body: JSON.stringify(payload),
      });

      setSuccessResult({
        admissions: result.data.used_admissions,
        voucherDetails: voucherDetails,
      });
      setVoucherDetails(null);
      setToken("");
      setOverrideDate(false);
      setOverrideReason("");
    } catch (err) {
      setError(err.message || "Kunjungan belum dapat divalidasi. Periksa status dan tanggal voucher.");
    } finally {
      setBusy(false);
    }
  }

  function resetForm() {
    setToken("");
    setVoucherDetails(null);
    setSuccessResult(null);
    setError("");
    setMessage("");
    setOverrideDate(false);
    setOverrideReason("");
    stopCamera();
  }

  const isSuperAdmin = profile?.platform_role === "super_admin";
  const isStaff = isSuperAdmin || profile?.partner_role || profile?.role === "admin";

  return (
    <Shell>
      <PageHeader
        eyebrow="Portal Petugas Lapangan"
        title="Validasi Voucher & Check-in Tiket"
        description="Pindai QR code voucher pengunjung atau masukkan kode token untuk memverifikasi kedatangan di lokasi wisata."
        compact
      />

      <div style={{ maxWidth: "640px", margin: "1.5rem auto" }}>
        {/* User Status Bar */}
        <div
          style={{
            padding: "0.85rem 1.25rem",
            backgroundColor: "#fff",
            border: "1px solid var(--color-border)",
            borderRadius: "0.75rem",
            display: "flex",
            justifyContent: "space-between",
            alignItems: "center",
            flexWrap: "wrap",
            gap: "0.5rem",
            marginBottom: "1.5rem",
          }}
        >
          {loadingProfile ? (
            <span style={{ fontSize: "0.85rem", color: "var(--color-text-muted)" }}>
              Memeriksa status akun petugas…
            </span>
          ) : profile ? (
            <div style={{ display: "flex", alignItems: "center", gap: "0.5rem", fontSize: "0.85rem" }}>
              <UserCheck size={18} color="#059669" />
              <span>
                Petugas: <strong>{profile.name}</strong> ({isSuperAdmin ? "Administrator Platform" : "Petugas / Mitra"})
              </span>
            </div>
          ) : (
            <div style={{ display: "flex", alignItems: "center", gap: "0.5rem", fontSize: "0.85rem", color: "#dc2626" }}>
              <AlertCircle size={18} />
              <span>Belum masuk ke akun petugas.</span>
            </div>
          )}

          {!profile && (
            <Link
              href="/login?redirect=/petugas"
              className="ui-button ui-button-primary"
              style={{ fontSize: "0.8rem", padding: "0.3rem 0.75rem" }}
            >
              Masuk Akun
            </Link>
          )}
        </div>

        {/* Success Screen */}
        {successResult && (
          <div
            style={{
              padding: "2rem 1.5rem",
              backgroundColor: "#ecfdf5",
              border: "1px solid #a7f3d0",
              borderRadius: "1rem",
              textAlign: "center",
              marginBottom: "1.5rem",
            }}
          >
            <div
              style={{
                width: "56px",
                height: "56px",
                borderRadius: "50%",
                backgroundColor: "#10b981",
                color: "#fff",
                display: "inline-flex",
                alignItems: "center",
                justifyContent: "center",
                marginBottom: "1rem",
              }}
            >
              <CheckCircle2 size={32} />
            </div>
            <h2 style={{ fontSize: "1.35rem", fontWeight: 700, color: "#065f46", margin: "0 0 0.5rem 0" }}>
              Kunjungan Berhasil Divalidasi!
            </h2>
            <p style={{ color: "#047857", fontSize: "0.95rem", margin: "0 0 1.25rem 0" }}>
              Voucher telah tercatat check-in untuk <strong>{successResult.admissions} peserta</strong>.
            </p>

            {successResult.voucherDetails && (
              <div
                style={{
                  maxWidth: "400px",
                  margin: "0 auto 1.5rem auto",
                  padding: "1rem",
                  backgroundColor: "#fff",
                  borderRadius: "0.5rem",
                  border: "1px solid #d1fae5",
                  textAlign: "left",
                  fontSize: "0.85rem",
                }}
              >
                <p style={{ margin: "0 0 0.35rem 0" }}>
                  <strong>Pengunjung:</strong> {successResult.voucherDetails.customer_name}
                </p>
                <p style={{ margin: "0 0 0.35rem 0" }}>
                  <strong>Layanan:</strong> {successResult.voucherDetails.product_name}
                </p>
                <p style={{ margin: 0 }}>
                  <strong>Destinasi / Mitra:</strong> {successResult.voucherDetails.partner_name}
                </p>
              </div>
            )}

            <button
              onClick={resetForm}
              className="ui-button ui-button-primary"
              style={{ display: "inline-flex", alignItems: "center", gap: "0.5rem", padding: "0.6rem 1.5rem" }}
            >
              <RefreshCw size={16} /> Validasi Voucher Berikutnya
            </button>
          </div>
        )}

        {/* Input & Scanner Panel */}
        {!successResult && (
          <div
            className="ui-card"
            style={{
              padding: "1.5rem",
              backgroundColor: "#fff",
              border: "1px solid var(--color-border)",
              borderRadius: "0.75rem",
              marginBottom: "1.5rem",
            }}
          >
            <h2 style={{ fontSize: "1.15rem", fontWeight: 700, margin: "0 0 0.5rem 0" }}>
              Pindai & Validasi Voucher
            </h2>
            <p style={{ fontSize: "0.85rem", color: "var(--color-text-muted)", margin: "0 0 1.25rem 0" }}>
              Arahkan kamera ke kode QR pengunjung untuk pemindaian instan, unggah foto QR, atau masukkan 48 karakter kode voucher secara manual.
            </p>

            {/* Live Camera Scanner Viewport */}
            {cameraActive && (
              <div
                style={{
                  position: "relative",
                  width: "100%",
                  borderRadius: "0.75rem",
                  overflow: "hidden",
                  backgroundColor: "#0f172a",
                  boxShadow: "0 10px 25px -5px rgba(0, 0, 0, 0.4)",
                  marginBottom: "1.5rem",
                  aspectRatio: "4/3",
                  maxHeight: "360px",
                }}
              >
                <video
                  ref={videoRef}
                  playsInline
                  autoPlay
                  muted
                  style={{
                    width: "100%",
                    height: "100%",
                    objectFit: "cover",
                  }}
                />

                {/* Viewfinder target box with glowing corners & laser beam */}
                <div
                  style={{
                    position: "absolute",
                    inset: 0,
                    display: "flex",
                    alignItems: "center",
                    justifyContent: "center",
                    pointerEvents: "none",
                  }}
                >
                  <div
                    style={{
                      position: "relative",
                      width: "210px",
                      height: "210px",
                      border: "2px solid rgba(16, 185, 129, 0.8)",
                      borderRadius: "0.75rem",
                      boxShadow: "0 0 0 9999px rgba(15, 23, 42, 0.55)",
                    }}
                  >
                    {/* Corner accents */}
                    <div style={{ position: "absolute", top: "-2px", left: "-2px", width: "20px", height: "20px", borderTop: "4px solid #10b981", borderLeft: "4px solid #10b981", borderTopLeftRadius: "0.5rem" }} />
                    <div style={{ position: "absolute", top: "-2px", right: "-2px", width: "20px", height: "20px", borderTop: "4px solid #10b981", borderRight: "4px solid #10b981", borderTopRightRadius: "0.5rem" }} />
                    <div style={{ position: "absolute", bottom: "-2px", left: "-2px", width: "20px", height: "20px", borderBottom: "4px solid #10b981", borderLeft: "4px solid #10b981", borderBottomLeftRadius: "0.5rem" }} />
                    <div style={{ position: "absolute", bottom: "-2px", right: "-2px", width: "20px", height: "20px", borderBottom: "4px solid #10b981", borderRight: "4px solid #10b981", borderBottomRightRadius: "0.5rem" }} />

                    {/* Laser scan line */}
                    <div className="qr-laser-line" />
                  </div>
                </div>

                {/* Top Control Bar in Viewfinder */}
                <div
                  style={{
                    position: "absolute",
                    top: "12px",
                    right: "12px",
                    display: "flex",
                    gap: "8px",
                    zIndex: 20,
                  }}
                >
                  {hasTorch && (
                    <button
                      type="button"
                      onClick={toggleTorch}
                      style={{
                        backgroundColor: torchOn ? "#f59e0b" : "rgba(15, 23, 42, 0.75)",
                        color: "#fff",
                        border: "none",
                        borderRadius: "50%",
                        width: "38px",
                        height: "38px",
                        display: "flex",
                        alignItems: "center",
                        justifyContent: "center",
                        cursor: "pointer",
                      }}
                      title={torchOn ? "Matikan Lampu Flash" : "Nyalakan Lampu Flash"}
                    >
                      <Zap size={18} />
                    </button>
                  )}
                  <button
                    type="button"
                    onClick={toggleCameraFacing}
                    style={{
                      backgroundColor: "rgba(15, 23, 42, 0.75)",
                      color: "#fff",
                      border: "none",
                      borderRadius: "50%",
                      width: "38px",
                      height: "38px",
                      display: "flex",
                      alignItems: "center",
                      justifyContent: "center",
                      cursor: "pointer",
                    }}
                    title="Ganti Kamera Depan / Belakang"
                  >
                    <SwitchCamera size={18} />
                  </button>
                  <button
                    type="button"
                    onClick={stopCamera}
                    style={{
                      backgroundColor: "rgba(220, 38, 38, 0.85)",
                      color: "#fff",
                      border: "none",
                      borderRadius: "50%",
                      width: "38px",
                      height: "38px",
                      display: "flex",
                      alignItems: "center",
                      justifyContent: "center",
                      cursor: "pointer",
                    }}
                    title="Tutup Kamera"
                  >
                    <X size={18} />
                  </button>
                </div>

                {/* Bottom guidance overlay */}
                <div
                  style={{
                    position: "absolute",
                    bottom: "12px",
                    left: "16px",
                    right: "16px",
                    textAlign: "center",
                    color: "#fff",
                    fontSize: "0.8rem",
                    backgroundColor: "rgba(15, 23, 42, 0.75)",
                    padding: "6px 12px",
                    borderRadius: "9999px",
                    pointerEvents: "none",
                  }}
                >
                  Arahkan QR Code voucher ke dalam bingkai hijau
                </div>
              </div>
            )}

            {/* Camera Error Banner with Interactive Troubleshooting Guide */}
            {cameraError && (
              <div
                style={{
                  padding: "1rem",
                  backgroundColor: "#fff7ed",
                  border: "1px solid #fed7aa",
                  borderRadius: "0.5rem",
                  color: "#9a3412",
                  fontSize: "0.85rem",
                  marginBottom: "1.25rem",
                }}
              >
                <div style={{ display: "flex", alignItems: "flex-start", gap: "0.5rem", marginBottom: "0.75rem" }}>
                  <AlertCircle size={18} color="#ea580c" style={{ flexShrink: 0, marginTop: "2px" }} />
                  <div>
                    <strong style={{ color: "#9a3412", fontSize: "0.9rem" }}>Akses Kamera Terhalang:</strong>
                    <p style={{ margin: "0.25rem 0 0 0", color: "#7c2d12" }}>{cameraError}</p>
                  </div>
                </div>

                <div
                  style={{
                    backgroundColor: "#fff",
                    border: "1px solid #ffedd5",
                    borderRadius: "0.5rem",
                    padding: "0.75rem 1rem",
                    fontSize: "0.8rem",
                    lineHeight: "1.5",
                    color: "#475569",
                  }}
                >
                  <strong style={{ display: "block", color: "#1e293b", marginBottom: "0.4rem" }}>
                    Cara mengaktifkan kamera di browser Anda:
                  </strong>
                  <ul style={{ margin: 0, paddingLeft: "1.2rem", display: "flex", flexDirection: "column", gap: "0.35rem" }}>
                    <li>
                      <strong>Google Chrome / Edge / Brave:</strong> Klik ikon setelan / gembok di sebelah kiri URL <code>localhost:3000</code> pada bilah alamat (address bar) &rarr; ubah <strong>Kamera</strong> menjadi <strong>Izinkan (Allow)</strong> &rarr; muat ulang (reload) halaman.
                    </li>
                    <li>
                      <strong>Safari (macOS):</strong> Buka menu bar atas <em>Safari</em> &rarr; <em>Pengaturan untuk Situs Web Ini... (Settings for This Website)</em> &rarr; di opsi <strong>Kamera</strong> pilih <strong>Izinkan (Allow)</strong>.
                    </li>
                    <li>
                      <strong>Setelan Mac:</strong> Buka <em>System Settings &rarr; Privacy & Security &rarr; Camera</em> &rarr; pastikan browser Anda dicentang aktif.
                    </li>
                  </ul>
                </div>

                <div style={{ marginTop: "0.75rem", display: "flex", gap: "0.5rem", alignItems: "center", flexWrap: "wrap" }}>
                  <button
                    type="button"
                    onClick={() => startCamera()}
                    className="ui-button"
                    style={{
                      padding: "0.4rem 0.85rem",
                      fontSize: "0.8rem",
                      backgroundColor: "#ea580c",
                      color: "#fff",
                      borderRadius: "0.375rem",
                      border: "none",
                      cursor: "pointer",
                      display: "inline-flex",
                      alignItems: "center",
                      gap: "0.35rem",
                    }}
                  >
                    <RefreshCw size={13} /> Coba Aktifkan Kamera Lagi
                  </button>
                  <span style={{ fontSize: "0.75rem", color: "#9a3412" }}>
                    atau gunakan tombol <strong>Pilih Foto QR</strong> di bawah jika tidak ingin membuka izin kamera.
                  </span>
                </div>
              </div>
            )}

            {/* Quick Action Buttons: Live Camera & Upload */}
            <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: "0.75rem", marginBottom: "1.25rem" }}>
              {!cameraActive ? (
                <button
                  type="button"
                  onClick={() => startCamera()}
                  disabled={checking || busy}
                  className="ui-button"
                  style={{
                    display: "flex",
                    alignItems: "center",
                    justifyContent: "center",
                    gap: "0.5rem",
                    padding: "0.75rem 1rem",
                    backgroundColor: "#059669",
                    color: "#fff",
                    fontWeight: 600,
                    borderRadius: "0.5rem",
                    fontSize: "0.85rem",
                  }}
                >
                  <Camera size={18} />
                  <span>Buka Kamera QR</span>
                </button>
              ) : (
                <button
                  type="button"
                  onClick={stopCamera}
                  className="ui-button ui-button-outline"
                  style={{
                    display: "flex",
                    alignItems: "center",
                    justifyContent: "center",
                    gap: "0.5rem",
                    padding: "0.75rem 1rem",
                    color: "#dc2626",
                    borderColor: "#fca5a5",
                    fontWeight: 600,
                    borderRadius: "0.5rem",
                    fontSize: "0.85rem",
                  }}
                >
                  <X size={18} />
                  <span>Tutup Kamera</span>
                </button>
              )}

              <label
                style={{
                  display: "flex",
                  alignItems: "center",
                  justifyContent: "center",
                  gap: "0.5rem",
                  padding: "0.75rem 1rem",
                  border: "1px solid var(--color-border)",
                  borderRadius: "0.5rem",
                  backgroundColor: "#f9fafb",
                  cursor: "pointer",
                  fontSize: "0.85rem",
                  color: "var(--color-text-main)",
                  fontWeight: 500,
                  margin: 0,
                }}
              >
                <Upload size={17} color="var(--color-primary)" />
                <span>Pilih Foto QR</span>
                <input
                  type="file"
                  accept="image/*"
                  onChange={scan}
                  disabled={checking || busy}
                  style={{ display: "none" }}
                />
              </label>
            </div>

            {/* Manual Code Input Form */}
            <form onSubmit={handleCheck}>
              <div style={{ marginBottom: "1rem" }}>
                <label style={{ display: "block", fontSize: "0.85rem", fontWeight: 600, marginBottom: "0.35rem" }}>
                  Atau Masukkan Kode Token (48 Karakter)
                </label>
                <div style={{ display: "flex", gap: "0.5rem" }}>
                  <input
                    type="text"
                    required
                    minLength={48}
                    maxLength={48}
                    autoComplete="off"
                    value={token}
                    onChange={(e) => setToken(e.target.value.trim())}
                    placeholder="Contoh: 2vJ1166q2nZ0FR0dxvk8TKl30ER6ROwM8lzskahPpM8afvsw"
                    className="ui-input"
                    disabled={checking || busy}
                    style={{ flex: 1, fontFamily: "monospace", fontSize: "0.85rem" }}
                  />
                  <button
                    type="submit"
                    disabled={checking || busy || token.length < 48}
                    className="ui-button ui-button-primary"
                    style={{ display: "inline-flex", alignItems: "center", gap: "0.4rem", whiteSpace: "nowrap" }}
                  >
                    {checking ? <Loader2 size={16} className="animate-spin" /> : <Search size={16} />}
                    {checking ? "Memeriksa…" : "Periksa"}
                  </button>
                </div>
              </div>
            </form>

            {/* Error Banner */}
            {error && (
              <div
                style={{
                  padding: "0.75rem 1rem",
                  backgroundColor: "#fef2f2",
                  border: "1px solid #fecaca",
                  borderRadius: "0.5rem",
                  color: "#991b1b",
                  fontSize: "0.85rem",
                  marginBottom: "1rem",
                  display: "flex",
                  alignItems: "flex-start",
                  gap: "0.5rem",
                }}
              >
                <AlertCircle size={18} style={{ flexShrink: 0, marginTop: "2px" }} />
                <span>{error}</span>
              </div>
            )}

            {/* Warning Message */}
            {message && (
              <div
                style={{
                  padding: "0.75rem 1rem",
                  backgroundColor: "#fffbeb",
                  border: "1px solid #fde68a",
                  borderRadius: "0.5rem",
                  color: "#92400e",
                  fontSize: "0.85rem",
                  marginBottom: "1rem",
                  display: "flex",
                  alignItems: "flex-start",
                  gap: "0.5rem",
                }}
              >
                <AlertTriangle size={18} style={{ flexShrink: 0, marginTop: "2px" }} />
                <span>{message}</span>
              </div>
            )}

            {/* Voucher Inspection Result Card */}
            {voucherDetails && (
              <div
                style={{
                  border: "1px solid #bfdbfe",
                  backgroundColor: "#f0f7ff",
                  borderRadius: "0.75rem",
                  padding: "1.25rem",
                  marginTop: "1rem",
                }}
              >
                <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: "0.75rem" }}>
                  <span style={{ fontSize: "0.8rem", fontWeight: 600, color: "#1e40af", textTransform: "uppercase" }}>
                    Rincian Voucher Pengunjung
                  </span>
                  <span
                    style={{
                      padding: "0.2rem 0.6rem",
                      borderRadius: "9999px",
                      fontSize: "0.75rem",
                      fontWeight: 700,
                      backgroundColor:
                        voucherDetails.status === "active" && voucherDetails.is_today
                          ? "#ecfdf5"
                          : voucherDetails.status === "active" && !voucherDetails.is_today
                          ? "#fef3c7"
                          : "#f3f4f6",
                      color:
                        voucherDetails.status === "active" && voucherDetails.is_today
                          ? "#059669"
                          : voucherDetails.status === "active" && !voucherDetails.is_today
                          ? "#d97706"
                          : "#dc2626",
                    }}
                  >
                    {voucherDetails.status === "active" && voucherDetails.is_today
                      ? "✓ Siap Check-in"
                      : voucherDetails.status === "active" && !voucherDetails.is_today
                      ? "⚠️ Jadwal Berbeda"
                      : "✕ Sudah Digunakan"}
                  </span>
                </div>

                <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: "0.75rem", fontSize: "0.85rem", marginBottom: "1rem" }}>
                  <div>
                    <span style={{ color: "var(--color-text-muted)", display: "block", fontSize: "0.75rem" }}>Nama Tamu</span>
                    <strong>{voucherDetails.customer_name}</strong>
                  </div>
                  <div>
                    <span style={{ color: "var(--color-text-muted)", display: "block", fontSize: "0.75rem" }}>Jumlah Peserta</span>
                    <strong>{voucherDetails.admissions} Tiket</strong>
                  </div>
                  <div>
                    <span style={{ color: "var(--color-text-muted)", display: "block", fontSize: "0.75rem" }}>Layanan</span>
                    <strong>{voucherDetails.product_name}</strong>
                  </div>
                  <div>
                    <span style={{ color: "var(--color-text-muted)", display: "block", fontSize: "0.75rem" }}>Tanggal Jadwal</span>
                    <strong style={{ color: voucherDetails.is_today ? "#059669" : "#d97706" }}>
                      {voucherDetails.service_date_formatted}
                    </strong>
                  </div>
                </div>

                {/* Manifest Peserta Rombongan di Scanner Petugas */}
                {voucherDetails.participants && voucherDetails.participants.length > 0 && (
                  <div
                    style={{
                      marginBottom: "1rem",
                      padding: "0.75rem",
                      backgroundColor: "#ffffff",
                      borderRadius: "0.5rem",
                      border: "1px solid #bfdbfe",
                    }}
                  >
                    <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: "0.4rem" }}>
                      <span style={{ fontSize: "0.75rem", fontWeight: 700, color: "#1e40af", textTransform: "uppercase" }}>
                        Manifest Peserta Rombongan ({voucherDetails.participants.length} Orang)
                      </span>
                    </div>
                    <div style={{ display: "flex", flexDirection: "column", gap: "0.35rem" }}>
                      {voucherDetails.participants.map((p, idx) => (
                        <div
                          key={idx}
                          style={{
                            fontSize: "0.8rem",
                            color: "#1e293b",
                            display: "flex",
                            justifyContent: "space-between",
                            alignItems: "center",
                            borderBottom: "1px dashed #e2e8f0",
                            paddingBottom: "3px",
                          }}
                        >
                          <div>
                            <span style={{ fontWeight: 600 }}>{idx + 1}. {p.name}</span>
                            {p.id_number && (
                              <span style={{ color: "#64748b", fontSize: "0.75rem", marginLeft: "6px" }}>
                                ({p.id_number})
                              </span>
                            )}
                          </div>
                          <div style={{ textAlign: "right", fontSize: "0.75rem", color: "#64748b" }}>
                            {p.phone || p.notes || ""}
                          </div>
                        </div>
                      ))}
                    </div>
                  </div>
                )}

                {/* If Date is different, allow Super Admin Override */}
                {!voucherDetails.is_today && voucherDetails.status === "active" && (
                  <div
                    style={{
                      padding: "0.75rem",
                      backgroundColor: "#fff",
                      border: "1px solid #fde68a",
                      borderRadius: "0.5rem",
                      marginBottom: "1rem",
                    }}
                  >
                    <p style={{ margin: "0 0 0.5rem 0", fontSize: "0.8rem", color: "#92400e" }}>
                      Tanggal tiket ({voucherDetails.service_date_formatted}) berbeda dari tanggal hari ini ({voucherDetails.today_date}).
                    </p>
                    {voucherDetails.can_override ? (
                      <div>
                        <label style={{ display: "flex", alignItems: "center", gap: "0.4rem", fontSize: "0.85rem", fontWeight: 600, cursor: "pointer" }}>
                          <input
                            type="checkbox"
                            checked={overrideDate}
                            onChange={(e) => setOverrideDate(e.target.checked)}
                          />
                          Izinkan check-in khusus (Persetujuan Administrator)
                        </label>
                        {overrideDate && (
                          <input
                            type="text"
                            placeholder="Alasan pengecualian (contoh: Kedatangan dipercepat atas izin mitra)"
                            value={overrideReason}
                            onChange={(e) => setOverrideReason(e.target.value)}
                            minLength={5}
                            required
                            className="ui-input"
                            style={{ fontSize: "0.8rem", marginTop: "0.4rem", width: "100%" }}
                          />
                        )}
                      </div>
                    ) : (
                      <p style={{ margin: 0, fontSize: "0.8rem", color: "#dc2626" }}>
                        Hanya Administrator Platform yang dapat menyetujui check-in di luar jadwal tanggal tiket.
                      </p>
                    )}
                  </div>
                )}

                {/* Confirm Action Button */}
                {voucherDetails.status === "active" && (voucherDetails.is_today || overrideDate) ? (
                  <button
                    onClick={redeem}
                    disabled={busy || (!voucherDetails.is_today && !overrideReason.trim())}
                    className="ui-button ui-button-primary"
                    style={{
                      width: "100%",
                      padding: "0.75rem",
                      fontSize: "0.95rem",
                      fontWeight: 600,
                      display: "flex",
                      alignItems: "center",
                      justifyContent: "center",
                      gap: "0.5rem",
                    }}
                  >
                    {busy ? <Loader2 size={18} className="animate-spin" /> : <CheckCircle2 size={18} />}
                    {busy ? "Memvalidasi Kunjungan…" : `Konfirmasi Check-in (${voucherDetails.admissions} Peserta)`}
                  </button>
                ) : voucherDetails.status === "redeemed" ? (
                  <button
                    disabled
                    className="ui-button"
                    style={{ width: "100%", backgroundColor: "#e5e7eb", color: "#9ca3af", cursor: "not-allowed" }}
                  >
                    Voucher Sudah Digunakan Sebelumnya
                  </button>
                ) : null}
              </div>
            )}
          </div>
        )}
      </div>
    </Shell>
  );
}
