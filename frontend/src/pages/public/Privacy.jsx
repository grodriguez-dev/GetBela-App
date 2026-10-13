import React from "react";
import MainLayout from "../../layouts/MainLayout";
import { Link } from "react-router-dom";
import { ArrowLeft } from "lucide-react";

const Privacy = () => {
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
              Política de Privacidad
            </h1>
            <p className="text-gray-600">
              Última actualización: {new Date().toLocaleDateString("es-ES")}
            </p>
          </div>

          <div className="space-y-6 text-gray-700 leading-relaxed">
            <section>
              <h2 className="text-xl font-bold text-purple-950 mb-2">
                1. Responsable del tratamiento
              </h2>
              <p>
                Esta plataforma de reserva de citas para estéticas es la responsable
                del tratamiento de los datos personales que nos proporcionas al
                registrarte y utilizar el servicio.
              </p>
            </section>

            <section>
              <h2 className="text-xl font-bold text-purple-950 mb-2">
                2. Datos que recopilamos
              </h2>
              <p>
                Recopilamos los datos que nos facilitas al crear tu cuenta, como tu
                nombre, correo electrónico, contraseña, nombre del negocio, tipo de
                negocio, modalidad de atención y la información que publiques en tu
                perfil.
              </p>
            </section>

            <section>
              <h2 className="text-xl font-bold text-purple-950 mb-2">
                3. Finalidad del tratamiento
              </h2>
              <p>
                Utilizamos tus datos para crear y gestionar tu cuenta, permitirte
                ofrecer tus servicios, gestionar las reservas de citas y comunicarnos
                contigo respecto al funcionamiento de la plataforma.
              </p>
            </section>

            <section>
              <h2 className="text-xl font-bold text-purple-950 mb-2">
                4. Base legal
              </h2>
              <p>
                El tratamiento de tus datos se basa en tu consentimiento al registrarte
                y en la ejecución del servicio que ofrecemos a través de la plataforma.
              </p>
            </section>

            <section>
              <h2 className="text-xl font-bold text-purple-950 mb-2">
                5. Conservación de los datos
              </h2>
              <p>
                Conservamos tus datos mientras mantengas tu cuenta activa y durante el
                tiempo necesario para cumplir con las obligaciones legales aplicables.
              </p>
            </section>

            <section>
              <h2 className="text-xl font-bold text-purple-950 mb-2">
                6. Compartición con terceros
              </h2>
              <p>
                No vendemos tus datos personales. Podemos compartirlos con proveedores
                tecnológicos que nos ayudan a operar la plataforma, siempre bajo
                acuerdos de confidencialidad y protección de datos.
              </p>
            </section>

            <section>
              <h2 className="text-xl font-bold text-purple-950 mb-2">
                7. Tus derechos
              </h2>
              <p>
                Puedes solicitar el acceso, la rectificación, la actualización o la
                eliminación de tus datos personales, así como oponerte a su
                tratamiento, escribiéndonos a través de la página de{" "}
                <Link
                  to="/contacto"
                  className="text-purple-950 font-semibold hover:underline"
                >
                  contacto
                </Link>
                .
              </p>
            </section>

            <section>
              <h2 className="text-xl font-bold text-purple-950 mb-2">
                8. Seguridad
              </h2>
              <p>
                Aplicamos medidas técnicas y organizativas razonables para proteger tus
                datos frente a accesos no autorizados, pérdida o alteración.
              </p>
            </section>

            <section>
              <h2 className="text-xl font-bold text-purple-950 mb-2">
                9. Cookies
              </h2>
              <p>
                La plataforma puede utilizar cookies y tecnologías similares para
                mantener tu sesión activa y mejorar tu experiencia de uso.
              </p>
            </section>

            <section>
              <h2 className="text-xl font-bold text-purple-950 mb-2">
                10. Cambios en esta política
              </h2>
              <p>
                Podemos actualizar esta Política de Privacidad en cualquier momento.
                Te recomendamos revisarla periódicamente para estar informado sobre
                cómo protegemos tus datos.
              </p>
            </section>
          </div>
        </div>
      </div>
    </MainLayout>
  );
};

export default Privacy;
