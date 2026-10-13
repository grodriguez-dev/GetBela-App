import React from "react";
import MainLayout from "../../layouts/MainLayout";
import { Link } from "react-router-dom";
import { ArrowLeft } from "lucide-react";

const Terms = () => {
  return (
    <MainLayout>
      <div className="max-w-4xl mx-auto px-4 py-8 flex-1 w-full">
        <div className="mb-6">
          <Link
            to="/register"
            className="inline-flex items-center gap-2 text-purple-950 hover:text-purple-700 transition-colors font-medium"
          >
            <ArrowLeft size={20} />
            Volver al registro
          </Link>
        </div>

        <div className="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 md:p-8">
          <div className="text-center mb-8">
            <h1 className="text-3xl md:text-4xl font-extrabold text-purple-950 mb-3">
              Términos y Condiciones
            </h1>
            <p className="text-gray-600">
              Última actualización: {new Date().toLocaleDateString("es-ES")}
            </p>
          </div>

          <div className="space-y-6 text-gray-700 leading-relaxed">
            <section>
              <h2 className="text-xl font-bold text-purple-950 mb-2">
                1. Aceptación de los términos
              </h2>
              <p>
                Al registrarte y utilizar esta plataforma de reserva de citas para
                estéticas, aceptas los presentes Términos y Condiciones. Si no estás
                de acuerdo con ellos, no debes utilizar el servicio.
              </p>
            </section>

            <section>
              <h2 className="text-xl font-bold text-purple-950 mb-2">
                2. Descripción del servicio
              </h2>
              <p>
                La plataforma permite a los negocios de estética y estilismo
                independiente gestionar su perfil, publicar sus servicios y recibir
                reservas de citas de sus clientes.
              </p>
            </section>

            <section className="bg-purple-50/60 border border-purple-100 rounded-xl p-4">
              <h2 className="text-xl font-bold text-purple-950 mb-2">
                3. Gratuidad del servicio
              </h2>
              <p>
                El uso de la plataforma es <strong>gratuito durante un tiempo
                indefinido</strong>. Sin embargo, nos reservamos el derecho de
                modificar esta condición en el futuro: en algún momento el servicio
                puede cambiar y pasar a requerir un <strong>pago</strong> para
                continuar usando la plataforma o determinadas funcionalidades.
              </p>
              <p className="mt-3">
                En caso de que el servicio deje de ser gratuito, te notificaremos
                previamente y de forma clara los nuevos precios y condiciones, para
                que puedas decidir si deseas continuar utilizando la plataforma.
              </p>
            </section>

            <section>
              <h2 className="text-xl font-bold text-purple-950 mb-2">
                4. Registro de cuenta
              </h2>
              <p>
                Para acceder al servicio debes crear una cuenta proporcionando
                información veraz y actualizada. Eres responsable de mantener la
                confidencialidad de tus credenciales y de toda actividad realizada
                desde tu cuenta.
              </p>
            </section>

            <section>
              <h2 className="text-xl font-bold text-purple-950 mb-2">
                5. Uso de la plataforma
              </h2>
              <p>
                Te comprometes a usar la plataforma de forma lícita y a no utilizarla
                para fines fraudulentos, ni para publicar contenido que infrinja
                derechos de terceros o la legislación vigente.
              </p>
            </section>

            <section>
              <h2 className="text-xl font-bold text-purple-950 mb-2">
                6. Reservas y citas
              </h2>
              <p>
                La gestión de las citas y la relación entre el negocio y sus clientes
                es responsabilidad directa del negocio. La plataforma actúa únicamente
                como herramienta de intermediación tecnológica.
              </p>
            </section>

            <section>
              <h2 className="text-xl font-bold text-purple-950 mb-2">
                7. Responsabilidad
              </h2>
              <p>
                La plataforma se ofrece "tal cual" y no garantiza la disponibilidad
                ininterrumpida del servicio. No nos hacemos responsables de los daños
                derivados del uso o de la imposibilidad de uso de la plataforma.
              </p>
            </section>

            <section>
              <h2 className="text-xl font-bold text-purple-950 mb-2">
                8. Propiedad intelectual
              </h2>
              <p>
                Todos los elementos de la plataforma (marca, diseño, software y
                contenidos) son propiedad de sus titulares y no pueden ser utilizados
                sin autorización.
              </p>
            </section>

            <section>
              <h2 className="text-xl font-bold text-purple-950 mb-2">
                9. Modificaciones de los términos
              </h2>
              <p>
                Podemos actualizar estos Términos y Condiciones en cualquier momento.
                Las modificaciones entrarán en vigor desde su publicación. El uso
                continuado del servicio implica la aceptación de los nuevos términos.
              </p>
            </section>

            <section>
              <h2 className="text-xl font-bold text-purple-950 mb-2">
                10. Contacto
              </h2>
              <p>
                Si tienes dudas sobre estos Términos y Condiciones, puedes
                contactarnos a través de la página de{" "}
                <Link
                  to="/contacto"
                  className="text-purple-950 font-semibold hover:underline"
                >
                  contacto
                </Link>
                .
              </p>
            </section>
          </div>
        </div>
      </div>
    </MainLayout>
  );
};

export default Terms;
