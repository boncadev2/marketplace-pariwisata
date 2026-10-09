import { ArrowUpRight, Compass } from "lucide-react";
import Link from "next/link";

function formatHeaderTitle(title) {
  if (typeof title !== "string") return title;
  const trimmed = title.trim();
  if (
    trimmed === "Temukan tempat. Ciptakan cerita." ||
    trimmed === "Temukan tempat. Ciptakan cerita"
  ) {
    return (
      <>
        <span>Temukan tempat.</span>{" "}
        <span>
          Ciptakan <span className="heading-accent">cerita.</span>
        </span>
      </>
    );
  }
  return title;
}

export function PageHeader({
  eyebrow,
  title,
  description,
  image,
  action,
  compact = false,
}) {
  return (
    <section
      className={`page-heading ${image ? "page-heading-image" : ""} ${compact ? "page-heading-compact" : ""}`}
    >
      <div className="page-heading-copy">
        <span className="heading-kicker">
          <Compass size={15} />
          {eyebrow || "WisataDaerah"}
        </span>
        <h1>{formatHeaderTitle(title)}</h1>
        {description && <p>{description}</p>}
        {action && (
          <Link className="ui-button ui-button-white" href={action.href}>
            {action.label}
            <ArrowUpRight size={16} />
          </Link>
        )}
      </div>
      {image && (
        <div
          className="page-heading-photo"
          style={{ backgroundImage: `url('${image}')` }}
          role="img"
          aria-label="Ilustrasi pengalaman wisata"
        />
      )}
    </section>
  );
}

export function EmptyState({
  title,
  description,
  icon: Icon = Compass,
  href,
  label,
  headingLevel: Heading = "h2",
}) {
  return (
    <div className="empty-state">
      <span className="empty-state-icon">
        <Icon size={30} />
      </span>
      <Heading>{title}</Heading>
      <p>{description}</p>
      {href && (
        <Link href={href} className="ui-button">
          {label || "Jelajahi destinasi"}
        </Link>
      )}
    </div>
  );
}
