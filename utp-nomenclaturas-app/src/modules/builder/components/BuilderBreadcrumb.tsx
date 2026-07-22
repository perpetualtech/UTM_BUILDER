import {
  Breadcrumb,
  BreadcrumbItem,
  BreadcrumbLink,
  BreadcrumbList,
  BreadcrumbPage,
  BreadcrumbSeparator,
} from "@/modules/core/components/design-system/breadcrumb";

interface Crumb {
  id: string;
  label: string;
  onClick?: () => void;
}

interface BuilderBreadcrumbProps {
  crumbs: Crumb[];
}

/** §8.2 del SDD: Builder → Breadcrumb del drill-down Pilar ▸ Campaña ▸ Conjunto. */
export function BuilderBreadcrumb({ crumbs }: BuilderBreadcrumbProps) {
  return (
    <Breadcrumb>
      <BreadcrumbList>
        {crumbs.map((crumb, index) => {
          const isLast = index === crumbs.length - 1;
          return (
            <span key={crumb.id} className="contents">
              <BreadcrumbItem>
                {isLast || !crumb.onClick ? (
                  <BreadcrumbPage>{crumb.label}</BreadcrumbPage>
                ) : (
                  <BreadcrumbLink onClick={crumb.onClick} className="cursor-pointer">
                    {crumb.label}
                  </BreadcrumbLink>
                )}
              </BreadcrumbItem>
              {!isLast ? <BreadcrumbSeparator /> : null}
            </span>
          );
        })}
      </BreadcrumbList>
    </Breadcrumb>
  );
}
